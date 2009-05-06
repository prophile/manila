<?php

class manila_driver_cluster extends manila_driver
{
	private function hash ( $key ) // returns a 31-bit hash
	{
		if (is_string($key))
		{
			return crc32($key) & 0x7FFFFFFF;
		}
		elseif (is_integer($key))
		{
			$v = $key ^ 0x35C64204;
			$rol = $v & 0x0000001F; // get the low-order 5 bits
			$rv = ($v << $rol) | ($v >> (32 - $rol)); // do a rotate left
			return $rv & 0x7FFFFFFF;
		}
		else
		{
			die("Cannot hash key of type: " . gettype($key));
		}
	}
	
	private $childen = array(); // name => manila_driver
	private $nodes = array(); // hash => name
	private $node_keys = array(); // sorted array of hash
	
	private $localchild; // responsible for all indices and key lists, not data nor metadata
	
	private $unique_id;
	
	private $duplication = 3;
	
	private function get_node ( $n )
	{
		// select first hash < $n
		// this uses a linear search, plan is to upgrade to a binary search
		$wv = min($this->node_keys);
		foreach ($this->node_keys as $k)
		{
			if ($k > $n)
			{
				$node = $this->nodes[$k];
				if ($wv == 0x7FFFFFFF)
					die("[CLUSTER] Bad WV in get_node loop, k=$k($node) n=$n\n");
				return $this->nodes[$wv];
			}
			$wv = $k;
		}
		if ($wv == 0x7FFFFFFF)
			die("[CLUSTER] Bad WV in get_node loop, k=$k n=$n\n");
		return $this->nodes[$wv];
	}
	
	private function select_nodes ( $key )
	{
		$count = $this->duplication;
		$hash = $this->hash($key);
		mt_srand($hash);
		$nodes = array();
		while (count($nodes) < $count)
		{
			$val = mt_rand() % 0x7FFFFFFF;
			$node = $this->get_node($val);
			if (!in_array($node, $nodes))
				$nodes[] = $node;
		}
		return $nodes;
	}
	
	public function __construct ( $driver_config, $table_config )
	{
		$this->unique_id = $driver_config['unique_id'];
		$this->localchild = manila::get_driver($driver_config['master']);
		if (isset($driver_config['duplication']))
			$this->duplication = $driver_config['duplication'];
		$subnodes = (array)$driver_config['child'];
		foreach ($subnodes as $child)
		{
			$hash = $this->hash($child);
			$this->children[$child] = manila::get_driver($child);
			$this->nodes[$hash] = $child;
			$this->node_keys[] = $hash;
		}
		sort($this->node_keys);
	}
	
	private $keycaches = array();
	
	private function push_keycache_changes ( $tname )
	{
		if (isset($this->keycaches[$tname]))
		{
			$d = serialize($this->keycaches[$tname]);
			$this->localchild->meta_write($this->unique_id . "_$tname" . "_keys", $d);
		}
	}
	
	private function get_keycache ( $tname )
	{
		$key = $this->unique_id . "_$tname" . "_keys";
		if (!isset($this->keycaches[$tname]))
		{
			$d = $this->localchild->meta_read($key);
			$this->keycaches[$tname] = ($d !== NULL) ? unserialize($d) : array();
		}
		return $this->keycaches[$tname];
	}
	
	public function table_list_keys ( $tname )
	{
		return array_keys($this->get_keycache($tname));
	}
	
	public function table_key_exists ( $tname, $key )
	{
		$kc = $this->get_keycache($tname);
		return isset($kc[$key]);
	}
	
	public function table_insert ( $tname, $values )
	{
		die("[CLUSTER] unable to use serial tables.\n");
	}
	
	public function table_update ( $tname, $key, $values )
	{
		$kc = $this->get_keycache($tname);
		if (!isset($kc[$key]))
		{
			$this->keycaches[$tname][$key] = true;
			$this->push_keycache_changes($tname);
		}
		$nodes = $this->select_nodes($key);
		foreach ($nodes as $node)
		{
			$this->children[$node]->table_update($tname, $key, $values);
		}
	}
	
	public function table_delete ( $tname, $key )
	{
		$kc = $this->get_keycache($tname);
		if (isset($kc[$key]))
		{
			unset($this->keycaches[$tname][$key]);
			$this->push_keycache_changes($tname);
		}
		$nodes = $this->select_nodes($key);
		foreach ($nodes as $node)
		{
			$this->children[$node]->table_delete($tname, $key, $values);
		}
	}
	
	public function table_truncate ( $tname )
	{
		foreach ($this->nodes as $node)
		{
			$this->children[$node]->table_truncate($tname);
		}
		$this->localchild->table_truncate($tname);
		$this->keycaches[$tname] = array();
		$this->push_keycache_changes($tname);
	}
	
	public function table_fetch ( $tname, $key )
	{
		$kc = $this->get_keycache($tname);
		if (!isset($kc[$key]))
			return NULL;
		$nodes = $this->select_nodes($key);
		shuffle($nodes);
		$duffers = array();
		foreach ($nodes as $node)
		{
			$d = $this->children[$node]->table_fetch($tname, $key);
			if ($d === NULL)
			{
				$duffers[] = $node;
			}
			else
			{
				foreach ($duffers as $duffer)
				{
					echo "[CLUSTER] Healed key $key in table $tname on sub-driver $duffer\n";
					$this->children[$duffer]->table_update($tname, $key, $d);
				}
				return $d;
			}
		}
		return NULL;
	}
	
	public function table_index_edit ( $tname, $field, $value, $key )
	{
		$this->localchild->table_index_edit($tname, $field, $value, $key);
	}
	
	public function table_index_lookup ( $tname, $field, $value )
	{
		return $this->localchild->table_index_lookup($tname, $field, $value);
	}
	
	public function table_optimise ( $tname )
	{
		foreach ($this->nodes as $node)
		{
			$this->children[$node]->table_optimise($tname);
		}
		$this->localchild->table_optimise($tname);
		// future: do healing
	}
	
	public function meta_write ( $key, $value )
	{
		$nodes = $this->select_nodes($key);
		foreach ($nodes as $node)
		{
			$this->children[$node]->meta_write($key, $value);
		}
	}
	
	public function meta_read ( $key )
	{
		$nodes = $this->select_nodes($key);
		$node = $nodes[array_rand($nodes)];
		$nodes = $this->select_nodes($key);
		$duffers = array();
		shuffle($nodes);
		foreach ($nodes as $node)
		{
			$d = $this->children[$node]->meta_read($key);
			if ($d === NULL)
			{
				$duffers[] = $node;
			}
			else
			{
				foreach ($duffers as $duffer)
				{
					$this->children[$duffer]->meta_write($key, $d);
				}
				return $d;
			}
		}
		return NULL;
	}
	
	public function meta_list ( $pattern )
	{
		$list = array();
		foreach ($this->children as &$child)
		{
			$arr = $child->meta_list($pattern);
			$list = array_merge($list, $arr);
		}
		return array_unique($list);
	}
}

?>
