<?php

require_once(MANILA_INCLUDE_PATH . '/interface_meta.php');
require_once(MANILA_INCLUDE_PATH . '/interface_tables.php');
require_once(MANILA_INCLUDE_PATH . '/interface_tables_serial.php');

abstract class manila_driver
{
	abstract public function __construct ( $driver_config, $table_config );
	
	public function conforms ( $interface )
	{
		$iface = "manila_interface_$interface";
		if ($this instanceof $iface)
			return true;
		else
			return false;
	}
	
	protected static function fs_escape ( $key )
	{
		$newkey = '';
		$len = strlen($key);
		for ($i = 0; $i < $len; $i++)
		{
			$c = $key[$i];
			$chr = ord($c);
			if ($chr >= 97 && $chr <= 122)
				$newkey .= $c;
			elseif ($chr >= 48 && $chr <= 57)
				$newkey .= $c;
			else
				$newkey .= sprintf('_%02x', $chr);
		}
		return $newkey;
		// this is commented out due to a PHP bug at the time of writing, see git history for the rest of the implementations
		//return preg_replace_callback('/[^a-z0-9]/', array('manila_driver', '__fs_escape_callback'), $key);
	}
	
	protected static function fs_unescape ( $key )
	{
		$newkey = '';
		$len = strlen($key);
		for ($i = 0; $i < $len; $i++)
		{
			$c = $key[$i];
			if ($c == '_')
			{
				$temp = $key[$i + 1] . $key[$i + 2];
				$newkey .= chr(hexdec($temp));
				$i += 2;
			}
			else
			{
				$newkey .= $c;
			}
		}
		return $newkey;
	}
}