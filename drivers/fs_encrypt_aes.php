<?php

class manila_driver_fs_encrypt_aes extends manila_driver implements manila_interface_filesystem
{
	private $child = NULL;
	private $module;
	private $key;
	private $iv;
	
	public function __construct ( $driver_config, $table_config )
	{
		$this->child = manila::get_driver($driver_config['child'], $table_config, array('filesystem'));
		$password = $driver_config['password'];
		$this->iv = md5($password, true);
		$this->key = md5($password . 'k1', true) . md5($password . 'k2', true);
		$this->module = mcrypt_module_open(MCRYPT_RIJNDAEL_256, "", MCRYPT_MODE_CBC, "");
	}
	
	private function encrypt ( $data )
	{
		mcrypt_generic_init($this->module, $this->key, $this->iv);
		return mcrypt_generic($this->module, $data);
	}
	
	private function decrypt ( $data )
	{
		mcrypt_generic_init($this->module, $this->key, $this->iv);
		return mdecrypt_generic($this->module, $data);
	}
	
	public function file_exists ( $path )
	{
		return $this->child->file_exists($path);
	}
	
	public function file_read ( $path )
	{
		$content = $this->child->file_read($path);
		if (!$content)
			return NULL;
		return $this->decrypt($content);
	}
	
	public function file_write ( $path, $data )
	{
		$content = $this->encrypt($data);
		$this->child->file_write($path, $content);
	}
	
	public function file_erase ( $path )
	{
		$this->child->file_erase($path);
	}
	
	public function file_directory_list ( $dir )
	{
		return $this->child->file_directory_list($dir);
	}
}
