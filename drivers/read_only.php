<?php

class manila_driver_read_only extends manila_driver
{
	private $strict = false;
	private $child;
	
	public function __construct ( $driver_config, $table_config )
	{
		if (isset($driver_config[$strict]))
			$this->strict = $driver_config[$strict];
		$this->child = manila::get_driver($driver_config['child']);
	}
	
	private function violation ()
	{
		if ($this->strict)
		{
			die("ERROR: Manila read-only translator picked up a writing call, aborting\n");
		}
		else
		{
			echo "WARNING: Manila read-only translator picked up a writing call\n";
		}
	}

	public function table_list_keys ( $tname )
	{
		return $this->child->table_list_keys($tname);
	}

	public function table_key_exists ( $tname, $key )
	{
		return $this->child->table_list_keys($tname, $key);
	}

	public function table_insert ( $tname, $values )
	{
		$this->violation();
		return 1;
	}

	public function table_update ( $tname, $key, $values )
	{
		$this->violation();
	}

	public function table_delete ( $tname, $key )
	{
		$this->violation();
	}

	public function table_truncate ( $tname )
	{
		$this->violation();
	}

	public function table_fetch ( $tname, $key )
	{
		return $this->child->table_fetch($tname, $key);
	}

	public function table_index_edit ( $tname, $field, $value, $key )
	{
		$this->violation();
	}

	public function table_index_lookup ( $tname, $field, $value )
	{
		return $this->child->table_index_lookup($tname, $field, $value);
	}

	public function table_optimise ( $tname )
	{
		// silently ignore, because maintainance scripts might do this to all tables
	}

}

?>
