<?php

class manila_driver_logger extends manila_driver implements manila_interface_meta, manila_interface_tables, manila_interface_tables_serial
{
	private $child;
	
	private static function stringify ( $v )
	{
		ob_start();
		var_dump($v);
		$c = ob_get_contents();
		ob_end_clean();
		return trim($c);
	}
	
	public function __construct ( $driver_config, $table_config )
	{
		$child = manila::get_driver($driver_config['child']);
		$this->child = $child;
		$msg = sprintf("[LOGGER(%s)] Started\n", get_class($child));
		echo $msg;
	}
	
	public function table_list_keys ( $tname )
	{
		$msg = sprintf("[LOGGER(%s)] KeyList(%s)", get_class($this->child), $tname);
		$rv = $this->child->table_list_keys($tname);
		$msg .= sprintf(" = %s\n", self::stringify($rv));
		echo $msg;
		return $rv;
	}
	
	public function table_key_exists ( $tname, $key )
	{
		$msg = sprintf("[LOGGER(%s)] KeyExists(%s, %s)", get_class($this->child), $tname, $key);
		$rv = $this->child->table_key_exists($tname);
		$msg .= sprintf(" = %s\n", self::stringify($rv));
		echo $msg;
		return $rv;
	}
	
	public function table_insert ( $tname, $values )
	{
		$msg = sprintf("[LOGGER(%s)] Insert(%s, %s)", get_class($this->child), $tname, self::stringify($values));
		$rv = $this->child->table_insert($tname, $values);
		$msg .= sprintf(" = %s\n", self::stringify($rv));
		echo $msg;
		return $rv;
	}
	
	public function table_update ( $tname, $key, $values )
	{
		$msg = sprintf("[LOGGER(%s)] Update(%s, %s, %s)\n", get_class($this->child), $tname, $key, self::stringify($values));
		$this->child->table_update($tname, $key, $values);
		echo $msg;
	}
	
	public function table_delete ( $tname, $key )
	{
		$msg = sprintf("[LOGGER(%s)] Delete(%s, %s)\n", get_class($this->child), $tname, $key);
		$this->child->table_delete($tname, $key);
		echo $msg;
	}
	
	public function table_truncate ( $tname )
	{
		$msg = sprintf("[LOGGER(%s)] Truncate(%s)\n", get_class($this->child), $tname);
		$this->child->table_truncate($tname);
		echo $msg;
	}
	
	public function table_fetch ( $tname, $key )
	{
		$msg = sprintf("[LOGGER(%s)] Fetch(%s, %s)", get_class($this->child), $tname, $key);
		$rv = $this->child->table_fetch($tname, $key);
		$msg .= sprintf(" = %s\n", self::stringify($rv));
		echo $msg;
		return $rv;
	}
	
	public function table_index_edit ( $tname, $field, $value, $key )
	{
		$msg = sprintf("[LOGGER(%s)] IndexEdit(%s, %s, %s => %s)\n", get_class($this->child), $tname, $field, $value, $key);
		$this->child->table_index_edit($tname, $field, $value, $key);
		echo $msg;
	}
	
	public function table_index_lookup ( $tname, $field, $value )
	{
		$msg = sprintf("[LOGGER(%s)] IndexLookup(%s, %s, %s)", get_class($this->child), $tname, $field, $value);
		$rv = $this->child->table_index_lookup($tname, $field, $value);
		$msg .= sprintf(" = %s\n", self::stringify($rv));
		echo $msg;
		return $rv;
	}
	
	public function table_optimise ( $tname )
	{
		$msg = sprintf("[LOGGER(%s)] Optimise(%s)\n", get_class($this->child), $tname);
		$this->child->table_optimise($tname);
		echo $msg;
	}
	
	public function meta_read ( $key )
	{
		$msg = sprintf("[LOGGER(%s)] MetaRead(%s)", get_class($this->child), $key);
		$rv = $this->child->meta_read($key);
		$msg .= sprintf(" = '%s'\n", $rv);
		echo $msg;
		return $rv;
	}
	
	public function meta_write ( $key, $value )
	{
		$msg = sprintf("[LOGGER(%s)] MetaWrite(%s, %s)\n", get_class($this->child), $key, self::stringify($value));
		$this->child->meta_write($key, $value);
		echo $msg;
	}
	
	public function meta_list ( $pattern )
	{
		$msg = sprintf("[LOGGER(%s)] MetaList(%s)", get_class($this->child), $pattern);
		$rv = $this->child->meta_list($pattern);
		$msg .= sprintf(" = '%s'\n", $rv);
		echo $msg;
		return $rv;
	}
}

?>
