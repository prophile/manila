<?php

class manila_driver_serialize extends manila_driver
{
	private $root;
	private $fps = array();
	
	private static function md5lt ( $a, $b ) // a < b
	{
		for ($i = 0; $i < 16; $i++)
		{
			$ca = ord($a[$i]);
			$cb = ord($b[$i]);
			if ($ca < $cb)
				return true;
			elseif ($ca > $cb)
				return false;
		}
		return false; // eq
	}
	
	private static function rm ( $fileglob )
	{
		if (is_string($fileglob))
		{
			if (is_file($fileglob))
			{
				return unlink($fileglob);
			}
			elseif (is_dir($fileglob))
			{
				$ok = self::rm("$fileglob/*");
				if (!$ok)
				{
					return false;
				}
				return rmdir($fileglob);
			}
			else
			{
				$matching = glob($fileglob);
				if ($matching === false)
				{
					trigger_error(sprintf('No files match supplied glob %s', $fileglob), E_USER_WARNING);
					return false;
				}       
				$rcs = array_map(array('manila_driver_serialize', 'rm'), $matching);
				if (in_array(false, $rcs))
				{
					return false;
				}
			}       
		}
		elseif (is_array($fileglob))
		{
			$rcs = array_map(array('manila_driver_serialize', 'rm'), $fileglob);
			if (in_array(false, $rcs))
			{
				return false;
			}
		}
		else
		{
			trigger_error('Param #1 must be filename or glob pattern, or array of filenames or glob patterns', E_USER_ERROR);
			return false;
		}

		return true;
	}
	
	public function __construct ( $driver_config, $table_config )
	{
		$this->root = $driver_config['directory'];
	}
	
	public function table_list_keys ( $tname )
	{
		$path = $this->root . '/' . $tname;
		$paths = glob($path . '/*.obj');
		foreach ($paths as $key => $item)
		{
			$paths[$key] = str_replace(array($path . '/', '.obj'), array('', ''), $item);
		}
		return $paths;
	}
	
	public function table_key_exists ( $tname, $key )
	{
		$path = $this->root . "/$tname/$key.obj";
		return file_exists($path);
	}
	
	private function get_index_fp ( $tname, $field )
	{
		$path = $this->root . "/$tname/.index.$field";
		if (isset($this->fps[$path]))
			return $this->fps[$path];
		if (!file_exists($path))
			return NULL;
		$fp = fopen($path, "r+");
		$this->fps[$path] = $fp;
		return $fp;
	}
	
	private function read_i32 ( $fp )
	{
		$data = fread($fp, 4);
		$data = unpack('Nvalue', $data);
		return $data['value'];
	}
	
	private function write_i32 ( $fp, $v )
	{
		$data = pack('N', $v);
		fwrite($fp, $data);
	}
	
	public function table_index_edit ( $tname, $field, $value, $key )
	{
		$fp = $this->get_index_fp($tname, $field);
		if ($fp === NULL)
		{
			$fp = fopen($this->root . "/$tname/.index.$field", "w");
			flock($fp, LOCK_EX);
			$this->write_i32($fp, 1);
			fwrite($fp, md5((string)$value, true));
			$this->write_i32($fp, 24);
			$d = serialize($key);
			$this->write_i32($fp, strlen($d));
			fwrite($fp, $d);
			return;
		}
		flock($fp, LOCK_EX);
		$hash = md5((string)$value, true);
		fseek($fp, 0, SEEK_SET);
		$count = $this->read_i32($fp);
		$hashes = array();
		for ($i = 0; $i < $count; $i++)
		{
			$h = fread($fp, 16);
			$p = $this->read_i32($fp);
			$hashes[$h] = $p;
		}
		$oldpos = ftell($fp);
		fseek($fp, 0, SEEK_END);
		$newpos = ftell($fp);
		$datalen = $newpos - $oldpos;
		fseek($fp, $oldpos, SEEK_SET);
		$data = fread($fp, $datalen);
		$padj = 0;
		if ($key !== NULL)
		{
			if (isset($hashes[$hash]))
				$padj = 0;
			else
				$padj = 20;
			$psn = $datalen + 4 + (20 * (isset($hashes[$hash]) ? $count : $count + 1));
			$hashes[$hash] = $psn - $padj;
			$sv = serialize($key);
			$data .= pack('N', strlen($sv));
			$data .= $sv;
		}
		else
		{
			if (isset($hashes[$hash]))
			{
				$padj = -20;
				unset($hashes[$hash]);
			}
			else
			{
				$padj = 0;
			}
		}
		ksort($hashes);
		ftruncate($fp, 0);
		$this->write_i32($fp, count($hashes));
		foreach ($hashes as $hash => $psn)
		{
			$psn += $padj;
			fwrite($fp, $hash);
			$this->write_i32($fp, $psn);
		}
		fwrite($fp, $data);
	}
	
	public function table_index_lookup ( $tname, $field, $value )
	{
		$fp = $this->get_index_fp($tname, $field);
		if ($fp === NULL)
			return NULL;
		flock($fp, LOCK_SH);
		$hash = md5((string)$value, true);
		fseek($fp, 0, SEEK_SET);
		$count = $this->read_i32($fp);
		$left = 0;
		$right = $count - 1;
		while ($left <= $right)
		{
			$mid = (($right-$left)/2)+$left;
			// seek the value at mid
			$loc = ($mid*20)+4;
			fseek($fp, $loc, SEEK_SET);
			$lhash = fread($fp, 16);
			if ($lhash == $hash)
			{
				$position = $this->read_i32($loc);
				fseek($fp, $position, SEEK_SET);
				$datalen = $this->read_i32($fp);
				$data = fread($fp, $datalen);
				return unserialize($data);
			}
			else if (self::md5lt($lhash, $hash))
			{
				$right = $mid-1;
			}
			else
			{
				$left = $mid+1;
			}
		}
		return NULL;
	}
	
	public function table_insert ( $tname, $values )
	{
		$serialpath = $this->root . "/$tname/.serial";
		if (file_exists($serialpath))
		{
			$id = file_get_contents($serialpath);
			$id = (int)$id;
			file_put_contents($serialpath, (string)($id + 1));
		}
		else
		{
			$id = 1;
			file_put_contents($serialpath, '2');
		}
		$this->table_update($tname, $id, $values);
		return $id;
	}
	
	public function table_update ( $tname, $key, $values )
	{
		$data = serialize($values);
		$path = $this->root . "/$tname/$key.obj";
		file_put_contents($path, $data);
	}
	
	public function table_delete ( $tname, $key )
	{
		$path = $this->root . "/$tname/$key.obj";
		@unlink($path);
	}
	
	public function table_truncate ( $tname )
	{
		self::rm($this->root . "/$tname/*.obj");
		self::rm($this->root . "/$tname/.index.*");
		self::rm($this->root . "/$tname/.serial");
		$this->fps = array();
	}
	
	public function table_fetch ( $tname, $key )
	{
		$path = $this->root . "/$tname/$key.obj";
		$content = @file_get_contents($path);
		if (!$content) return NULL;
		return unserialize($content);
	}
	
	private static function rebuild_index ( $path )
	{
		$fp = fopen($path, 'r');
		flock($fp, LOCK_SH);
		$count = $this->read_i32($fp);
		$hashindices = array();
		for ($i = 0; $i < $count; $i++)
		{
			$hash = fread($fp, 16);
			$pos = $this->read_i32($fp);
			$hashindices[$hash] = $pos;
		}
		$data = array();
		foreach ($hashindices as $hash => $pos)
		{
			fseek($fp, $pos, SEEK_SET);
			$len = $this->read_i32($fp);
			$d = fread($fp, $len);
			$data[$hash] = $d;
		}
		unset($hashindices);
		fclose($fp);
		ksort($hash);
		$fp = fopen($path, 'w');
		flock($fp, LOCK_EX);
		$posn = 4 + (20 * count($data));
		$this->write_i32($fp, count($data));
		foreach ($data as $key => $value)
		{
			fwrite($fp, $key);
			$this->write_i32($fp, $posn);
			$posn += 4;
			$posn += strlen($value);
		}
		reset($data);
		foreach ($data as $key => $value)
		{
			$this->write_i32($fp, strlen($value));
			fwrite($fp, $value);
		}
		fclose($fp);
	}
	
	public function table_optimise ( $tname )
	{
		$list = glob($this->root . "/*/.index.*");
		foreach ($list as $idx)
		{
			self::rebuild_index($path);
		}
	}
	
	public function meta_read ( $key )
	{
		$khash = md5($key);
		$path = $this->root . "/.meta.$khash";
		return file_get_contents($path);
	}
	
	public function meta_write ( $key, $value )
	{
		$khash = md5($key);
		$path = $this->root . "/.meta.$khash";
		if ($value === NULL && file_exists($path))
		{
			unlink($path);
		}
		else
		{
			file_put_contents($path, $value);
		}
	}
}

?>
