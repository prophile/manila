<?php

class manila_driver_sink extends manila_driver implements manila_interface_meta, manila_interface_tables, manila_interface_tables_serial, manila_interface_filesystem
{
	public function __construct ( $driver_config, $table_config )
	{
	}
	
	public function table_list_keys ( $tname )
	{
		return array();
	}
	
	public function table_key_exists ( $tname, $key )
	{
		return false;
	}
	
	public function table_insert ( $tname, $values )
	{
		return 1;
	}
	
	public function table_update ( $tname, $key, $values )
	{
	}
	
	public function table_delete ( $tname, $key )
	{
	}
	
	public function table_truncate ( $tname )
	{
	}
	
	public function table_fetch ( $tname, $key )
	{
		return NULL;
	}
	
	public function table_index_edit ( $tname, $field, $value, $key )
	{
	}
	
	public function table_index_lookup ( $tname, $field, $value )
	{
		return NULL;
	}
	
	public function table_optimise ( $tname )
	{
	}
	
	public function meta_write ( $key, $value )
	{
	}
	
	public function meta_read ( $key )
	{
		return NULL;
	}
	
	public function meta_list ( $pattern )
	{
		return array();
	}
	
	public function file_exists ( $path )
	{
		return false;
	}
	
	public function file_read ( $path )
	{
		return NULL;
	}
	
	public function file_write ( $path, $data )
	{
	}
	
	public function file_erase ( $path )
	{
	}
	
	public function file_directory_list ( $dir )
	{
		return array();
	}
}

?>
