<?php

interface manila_interface_filesystem
{
	public function file_exists ( $path );
	public function file_read ( $path );
	public function file_write ( $path, $data );
	public function file_erase ( $path );
	public function file_directory_list ( $dir ); 
}

?>
