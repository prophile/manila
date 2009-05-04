<?php

class manila_driver_background extends manila_driver
{
	private $child;
	private $bg = false;
	
	public function __construct ( $driver_config, $table_config )
	{
		$this->child = manila::get_driver($driver_config['child']);
	}

	public function table_list_keys ( $tname )
	{
		return $this->child->table_list_keys($tname);
	}

	public function table_key_exists ( $tname, $key )
	{
		return $this->child->table_key_exists($tname, $key);
	}
	
	private function background_begin ()
	{
		if (!function_exits('pcntl_fork')) // no fork(), act as if fork failed
			return false;
		$pid = pcntl_fork();
		if ($pid > 0)
		{
			// parent process, continue on
			return true;
		}
		elseif ($pid == 0)
		{
			// child process, begin bg processing and continue
			$this->bg = true;
			return false;
		}
		else
		{
			// bg work then continue, but do not exit
			return false;
		}
	}
	
	private function background_end ()
	{
		if ($this->bg) exit();
	}

	public function table_insert ( $tname, $values )
	{
		return $this->child->table_insert($tname, $values);
	}

	public function table_update ( $tname, $key, $values )
	{
		if ($this->background_begin()) return;
		$this->child->table_update($tname, $key, $values);
		$this->background_end();
	}

	public function table_delete ( $tname, $key )
	{
		if ($this->background_begin()) return;
		$this->child->table_delete($tname, $key);
		$this->background_end();
	}

	public function table_truncate ( $tname )
	{
		if ($this->background_begin()) return;
		$this->child->table_truncate($tname);
		$this->background_end();
	}

	public function table_fetch ( $tname, $key )
	{
		return $this->child->table_fetch($tname, $key);
	}

	public function table_index_edit ( $tname, $field, $value, $key )
	{
		if ($this->background_begin()) return;
		$this->child->table_index_edit($tname, $field, $value, $key);
		$this->background_end();
	}

	public function table_index_lookup ( $tname, $field, $value )
	{
		return $this->child->table_index_lookup($tname, $field, $value);
	}

	public function table_optimise ( $tname )
	{
		if ($this->background_begin()) return;
		$this->child->table_optimise($tname);
		$this->background_end();
	}

	public function meta_read ( $key )
	{
		return $this->child->meta_read($key);
	}
	
	public function meta_write ( $key, $value )
	{
		// meta writes are so trivial there is no point backgrounding
		$this->child->meta_write($key, $value);
	}
}

?>
