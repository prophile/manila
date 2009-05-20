<?php

interface manila_interface_tables_serial extends manila_interface_tables
{
	public function table_insert ( $tname, $values ); // return key, this only used for serial keys
}

?>
