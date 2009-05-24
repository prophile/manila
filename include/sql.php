<?php

require_once(MANILA_INCLUDE_PATH . '/dqml2tree.php');

function __manila_sql_exec_clause_where ( &$table, $branch )
{
	if ($branch == NULL)
		return $table->list_keys();
	$field = $branch['0|!EQ']['FIELD'];
	$value = json_decode($branch['1|!EQ']['FIELD']);
	if ($field == $table->get_key_field_name())
	{
		return array($value);
	}
	else
	{
		return (array)$table->lookup($field, $value);
	}
}

function __manila_sql_exec_branch_delete ( &$obj, $branch )
{
	if (isset($branch['FROM']) && count($branch['FROM']) == 1)
		$from = $branch['FROM']['TABLE'];
	else
		return false;
	if (isset($branch['WHERE']))
	{
		$targetKey = $branch['WHERE']['1|!EQ'];
		$table =& $obj->table($from);
		$table->edit($targetKey, NULL);
	}
	else
	{
		$table =& $obj->table($from);
		$table->truncate();
		return true;
	}
}

function __manila_sql_exec_branch_select ( &$obj, $branch )
{
	$table =& $obj->table($branch['FROM']['TABLE']);
	$keys = __manila_sql_exec_clause_where($table, isset($branch['WHERE']) ? $branch['WHERE'] : NULL);
	// just fetch everything
	$content = array();
	foreach ($keys as $key)
	{
		$content[] = $table->fetch($key);
	}
	return $content;
}

function __manila_sql_exec_branch_update ( &$obj, $branch )
{
	$target = $branch["0|*UPDATE"]['TABLE'];
	$table =& $obj->table(strtolower($target));
	$keys = __manila_sql_exec_clause_where($table, isset($branch['WHERE']) ? $branch['WHERE'] : NULL);
	$updates = array();
	$set = $branch['SET'];
	if (isset($set['0|#SET']['FIELD']))
	{
		// single update
		$updates[$set['0|#SET']['FIELD']] = json_decode($set['1|#SET']);
	}
	else
	{
		foreach ($set as $subset)
		{
			$updates[$subset['0|#SET']['FIELD']] = json_decode($subset['1|#SET']);
		}
	}
	if (empty($keys))
		return false;
	foreach ($keys as $key)
	{
		$data = $table->fetch($key);
		$data = array_merge($data, $updates);
		$table->edit($key, $data);
	}
	return true;
}

function __manila_sql_exec_branch_insert ( &$obj, $branch )
{
	$into = $branch['INTO'];
	if (isset($into['FIELD']))
	{
		$table =& $obj->table(strtolower($into['FIELD']));
		$keyfield = $table->get_key_field_name();
		$keys = array($keyfield);
		$keys += $table->list_fields();
	}
	else
	{
		list($tname, $settings) = each($into);
		$table =& $obj->table(strtolower($tname));
		$keyfield = $table->get_key_field_name();
		$keys = array();
		foreach ($settings as $setting)
		{
			$keys[] = $setting['FIELD'];
		}
	}
	$values = $branch['VALUES'];
	$vals = array();
	foreach ($values['VALUES'] as $value)
	{
		$vals[] = json_decode($value['FIELD']);
	}
	if (!in_array($keyfield, $keys))
	{
		$keys = array($keyfield) + $keys;
		$vals = array(NULL) + $vals;
	}
	$assoc = array_combine($keys, $vals);
	$k = $assoc[$keyfield];
	unset($assoc[$keyfield]);
	$table->edit($k, $assoc);
	return true;
}

function __manila_sql_exec_branch ( &$obj, $branch )
{
	if (isset($branch['DELETE']))
	{
		return __manila_sql_exec_branch_delete($obj, $branch['DELETE']);
	}
	elseif (isset($branch['SELECT']))
	{
		return __manila_sql_exec_branch_select($obj, $branch['SELECT']);
	}
	elseif (isset($branch['UPDATE']))
	{
		return __manila_sql_exec_branch_update($obj, $branch['UPDATE']);
	}
	elseif (isset($branch['INSERT']))
	{
		return __manila_sql_exec_branch_insert($obj, $branch['INSERT']);
	}
}

function __manila_sql ( &$obj, $statement )
{
	$oldLevel = error_reporting(0);
	$stmtTree = new dqml2tree($statement);
	$stmtTree = $stmtTree->make();
	error_reporting($oldLevel);
	$results = array();
	foreach ($stmtTree as $treeBranch)
	{
		$results[] = __manila_sql_exec_branch($obj, $treeBranch);
	}
	if (count($results) == 1)
		return array_pop($results);
	else
		return $results;
}

?>
