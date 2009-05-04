<?php

class manila_driver_passthrough
{
	private $child;
	
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

	public function table_insert ( $tname, $values )
	{
		return $this->child->table_insert($tname, $values);
	}

	public function table_update ( $tname, $key, $values )
	{
		$this->child->table_update($tname, $key, $values);
	}

	public function table_delete ( $tname, $key )
	{
		$this->child->table_delete($tname, $key);
	}

	public function table_truncate ( $tname )
	{
		$this->child->table_truncate($tname);
	}

	public function table_fetch ( $tname, $key )
	{
		return $this->child->table_fetch($tname, $key);
	}

	public function table_index_edit ( $tname, $field, $value, $key )
	{
		$this->child->table_index_edit($tname, $field, $value, $key);
	}

	public function table_index_lookup ( $tname, $field, $value )
	{
		return $this->child->table_index_lookup($tname, $field, $value);
	}

	public function table_optimise ( $tname )
	{
		$this->child->table_optimise($tname);
	}

	public function meta_write ( $key, $value )
	{
		$this->child->meta_write($key, $value);
	}

	public function meta_read ( $key )
	{
		return $this->child->meta_read($key);
	}
}

?>