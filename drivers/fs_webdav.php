<?php

require_once(MANILA_INCLUDE_PATH . '/class_webdav_client.php');

class manila_driver_fs_webdav extends manila_driver implements manila_interface_filesystem
{
	private $webdav;
	private $root;
	
	public function __construct ( $driver_config, $table_config )
	{
		$this->webdav = new webdav_client;
		$this->webdav->set_server($driver_config['server']);
		$this->webdav->set_port(isset($driver_config['port']) ? $driver_config['port'] : 80);
		$this->webdav->set_user($driver_config['user']);
		$this->webdav->set_pass($driver_config['pass']);
		$this->webdav->set_debug(0);
		$this->webdav->set_protocol(1);
		$this->root = $driver_config['root'];
		$this->webdav->open();
	}
	
	public function __destruct ()
	{
		$this->webdav->close();
	}
	
	private function _mkdir_recursive ( $dir )
	{
		$parent = dirname($dir);
		if ($parent == $dir)
			return;
		if (!$this->webdav->is_dir($parent))
		{
			$this->_mkdir_recursive($parent);
		}
		$this->webdav->mkcol($dir);
	}
	
	public function file_exists ( $path )
	{
		$path = $this->root . "/$path";
		return $this->webdav->is_file($path);
	}
	
	public function file_read ( $path )
	{
		$path = $this->root . "/$path";
		$status = $this->webdav->get($path, $buffer);
		if ($status)
			return $buffer;
		else
			return NULL;
	}
	
	public function file_write ( $path, $data )
	{
		$this->_mkdir_recursive($this->root . "/$path");
		$success = $this->webdav->put($this->root . "/$path", $data);
		if (!$success)
			die("Failed to write file: $path");
	}
	
	public function file_erase ( $path )
	{
		$this->webdav->delete($this->root . "/$path");
	}
	
	public function file_directory_list ( $path )
	{
		$path = $this->root . "/$path";
		$contents = $this->webdav->ls($path);
		if ($contents === false) return array();
		return $contents;
	}
}
