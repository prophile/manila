<?php

class manila_index
{
	private $dataset = array();
	private $dirty = false;
	
	public function __construct ( $data = NULL )
	{
		if ($data)
		{
			$this->dataset = unserialize($data);
		}
	}
	
	public function rebuild ()
	{
	}
	
	public function set ( $key, $value )
	{
		if ($value)
			$this->dataset[md5((string)$key, true)] = $value;
		else
			unset($this->dataset[md5((string)$key, true)]);
		$this->dirty = true;
	}
	
	public function get ( $key )
	{
		$k = md5((string)$key, true);
		return isset($this->dataset[$k]) ? $this->dataset[$k] : NULL;
	}
	
	public function was_changed ()
	{
		return $this->dirty;
	}
	
	public function to_data ()
	{
		return serialize($this->dataset);
	}
}

?>
