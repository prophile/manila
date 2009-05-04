<?php

class manila_driver_duplicate extends manila_driver
{
	private $children = array();
	private $table_list;

	public function __construct ( $driver_config, $table_config )
	{
		foreach ($driver_config['child'] as $child)
		{
			$this->children[] = manila::get_driver($child);
		}
		$this->table_list = array_keys($table_config);
	}
	
	private function select_child ()
	{
		return mt_rand(0, count($this->children) - 1);
	}
	
	public function table_list_keys ( $tname )
	{
		$part = $this->select_child();
		return $this->children[$part]->table_list_keys($tname);
	}
	
	public function table_key_exists ( $tname, $key )
	{
		$part = $this->select_child();
		return $this->children[$part]->table_key_exists($tname, $key);
	}
	
	public function table_insert ( $tname, $values )
	{
		$master = $this->select_child();
		$id = $this->children[$master]->table_insert($tname, $values);
		foreach ($this->children as $key => &$child)
		{
			if ($key != $master)
			{
				$child->table_edit($tname, $id, $values);
			}
		}
		return $id;
	}
	
	public function table_update ( $tname, $key, $values )
	{
		foreach ($this->children as &$child)
		{
			$child->table_update($tname, $key, $values);
		}
	}
	
	public function table_delete ( $tname, $key )
	{
		foreach ($this->children as &$child)
		{
			$child->table_delete($tname, $key);
		}
	}
	
	public function table_truncate ( $tname )
	{
		foreach ($this->children as &$child)
		{
			$child->table_truncate($tname);
		}
	}
	
	public function table_fetch ( $tname, $key )
	{
		$part = $this->select_child();
		return $this->children[$part]->table_fetch($tname, $key);
	}
	
	public function table_index_edit ( $tname, $field, $value, $key )
	{
		$part = $this->select_child();
		$this->children[$part]->table_index_edit($tname, $field, $value, $key);
	}
	
	public function table_index_lookup ( $tname, $field, $value )
	{
		$part = $this->select_child();
		return $this->children[$part]->table_index_lookup($tname, $field, $value);
	}
	
	private static function occmap_compare ( $v1, $v2 )
	{
		$c1 = count($v1);
		$c2 = count($v2);
		if ($c1 < $c2) return -1;
		elseif ($c1 > $c2) return 1;
		else return 0;
	}
	
	private static function select_correct ( $values, &$fixers )
	{
		// values is an associative array
		// this maps values to an array of keys
		// selects one with the most keys
		// returns all other keys not with that value
		$occurence_map = array();
		foreach ($values as $key => $value)
		{
			if (isset($occurrence_map[$value]))
			{
				$occurrence_map[$value] = array($key);
			}
			else
			{
				$occurrence_map[$value][] = $key;
			}
		}
		usort($occurrence_map, array('manila_driver_duplicate', 'occmap_compare'));
		$selection = each($occurrence_map);
		array_pop($occurrence_map);
		$targets = array();
		foreach ($occurrence_map as $list)
		{
			$targets = $targets + $list;
		}
		$fixers = $targets;
		return $selection[0];
	}
	
	private function table_heal ( $tname )
	{
		$keylists = array();
		foreach ($this->children as $key => &$child)
		{
			$keylist = $child->table_list_keys($tname);
			$keylists[$key] = crc32(implode(';', $keylist));
			unset($keylist);
		}
		$keylists = array_unique($keylists);
		if (count($keylists) > 1)
		{
			printf("[DUPLICATE] Healing key list.\n");
			// select best child, heal other children
			$best = self::select_correct($keylists, $fixers);
			$best =& $this->children[$best];
			foreach ($fixers as $fixer)
			{
				$child =& $this->children[$fixer];
				$fixer->table_truncate();
			}
			$keys = $best->table_list_keys($tname);
			foreach ($keys as $k)
			{
				$values = $best->table_fetch($tname, $k);
				foreach ($fixers as $fixer)
				{
					$child =& $this->children[$fixer];
					$child->table_update($tname, $k, $values);
					// TODO: update all indices
				}
			}
			$truelist = $keys;
		}
		else
		{
			$truelist = $this->children[$this->select_child()]->table_list_keys($tname);
		}
		foreach ($truelist as $key)
		{
			$values = array();
			foreach ($this->children as $ckey => &$child)
			{
				$value = $child->table_fetch($tname, $key);
				$values[$ckey] = serialize($value);
				unset($value);
			}
			$values = array_unique($values);
			if (count($values) > 1)
			{
				printf("[DUPLICATE] Healing key %s\n", $key);
				// select best child, heal other children
				$best = self::select_correct($values, $fixers);
				$refvalues = unserialize($values[$best]);
				foreach ($fixers as $fixer)
				{
					$this->children[$fixer]->table_update($tname, $key, $refvalues);
					// TODO: update all indices
				}
			}
		}
	}
	
	public function table_optimise ( $tname )
	{
		foreach ($this->children as &$child)
		{
			$child->table_optimise($tname);
		}
		// do healing of all tables
		$this->table_heal($tname);
		// TODO: heal metadata?
	}
	
	public function meta_read ( $key )
	{
		$part = $this->select_child();
		return $this->children[$part]->meta_read($key);
	}
	
	public function meta_write ( $key, $value )
	{
		foreach ($this->children as &$child)
		{
			$child->meta_write($key, $value);
		}
	}
}

?>
