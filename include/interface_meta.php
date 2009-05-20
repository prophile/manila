<?php

interface manila_interface_meta
{
	public function meta_write ( $key, $value );
	public function meta_read ( $key );
	public function meta_list ( $pattern );
}

?>
