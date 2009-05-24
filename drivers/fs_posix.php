<?php

class manila_driver_fs_posix extends manila_driver implements manila_interface_filesystem
{
	private $root;
	
	private static function mkdir_recursive ( $dir )
	{
		if (!file_exists($dir))	
			@mkdir($dir, 0777, true);
	}
	
	public function __construct ( $driver_config, $table_config )
	{
		$this->root = $driver_config['root'];
		if (!file_exists($this->root))
		{
			self::mkdir_recursive($this->root);
		}
	}
	
	public function file_exists ( $path )
	{
		return file_exists($this->root . "/$path");
	}
	
	public function file_read ( $path )
	{
		if (file_exists($this->root . "/$path"))
			return file_get_contents($this->root . "/$path");
		else
			return NULL;
	}
	
	public function file_write ( $path, $data )
	{
		$dir = dirname($path);
		self::mkdir_recursive($this->root . "/$dir");
		file_put_contents($this->root . "/$path", $data);
	}
	
	public function file_erase ( $path )
	{
		$path = $this->root . "/$path";
		if (file_exists($this->root . "/$path"))
		{
			unlink($this->root . "/$path");
			while (($path = dirname($path)) != '/')
			{
				$success = @rmdir($path);
				if (!$success)
					return;
			}
		}
	}
	
	public function file_directory_list ( $dir )
	{
		$path = $this->root . "/$dir";
		$contents = file_exists($path) ? scandir($path) : NULL;
		if (!$contents)
			return array();
		foreach ($contents as $key => $value)
		{
			if ($value == '' || $value == '.' || $value == '..')
				unset($contents[$key]);
		}
		return $contents;
	}
}

?>
