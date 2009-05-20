<?php

interface manila_interface_tables
{
	public function table_key_exists ( $tname, $key );
	public function table_update ( $tname, $key, $values );
	public function table_delete ( $tname, $key );
	public function table_truncate ( $tname ); // includes flushing any indices
	public function table_fetch ( $tname, $key );
	public function table_index_edit ( $tname, $field, $value, $key );
	public function table_index_lookup ( $tname, $field, $value );
	public function table_optimise ( $tname );
}

?>