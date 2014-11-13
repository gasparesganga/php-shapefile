<?php
/**
* ----------------------------------------------------------------
*			XBase
*			api_conversion.php	
* 
*  Developer        : Erwin Kooi
*  released at      : Jan 2006
*  last modified by : Erwin Kooi
*  date modified    : Jan 2006
*
*  You're free to use this code as long as you don't alter it
*  Copyright (c) 2005 Cyane Dynamic Web Solutions
*  Info? Mail to info@cyane.nl
* 
* --------------------------------------------------------------
*
* This file implements the default dBase functions as described in the PHP docs
*
**/

require_once "Column.class.php";
require_once "Record.class.php";
require_once "Table.class.php";
require_once "WritableTable.class.php";

function xbase_add_record($xbase_identifier=false,$record) { // - Add a record (array of values) to a dBase database
	if (!($xbase=&xbase_getInstance($xbase_identifier))) return false;
	$r =& $xbase->appendRecord();
	foreach ($record as $i=>$v) {
		if (is_object($i))
			$r->setString($i,$v);
		else if (is_numeric($i)) 
			$r->setStringByIndex($i,$v);
		else 
			$r->setStringByName($i,$v);
	}
	$xbase->writeRecord();
}
function xbase_close($xbase_identifier=false) { // - Close a dBase database
	if (!($xbase=&xbase_getInstance($xbase_identifier))) return false;
	$xbase->close();
}
function xbase_create($filename,$fields) { // - Creates a dBase database
	if ($xbase =& XBaseWritableTable::create($filename,$fields)) return xbase_addInstance($xbase);
	return false;
}
function xbase_delete_record($xbase_identifier=false,$record) { // - Deletes a record from a dBase database
	if (!($xbase=&xbase_getInstance($xbase_identifier))) return false;
	$xbase->moveTo($record-1);
	$xbase->deleteRecord();
}
function xbase_get_header_info($xbase_identifier=false) { // - Get the header info of a dBase database
	if (!($xbase=&xbase_getInstance($xbase_identifier))) return false;
	$result = array();
	foreach ($xbase->columns as $column) {
		$result[] = array(
			"name"=>$column->name, 
			"type"=>$column->type, 
			"length"=>$column->length, 
			"precision"=>$column->decimalCount, 
			"format"=>"%s", 
			"offset"=>$column->bytePos,
		);
	}
	return $result;
}
function xbase_get_record_with_names($xbase_identifier=false,$record) { // - Gets a record from a dBase database as an associative array 
	if (!($xbase=&xbase_getInstance($xbase_identifier))) return false;
	$r =& $xbase->moveTo($record-1);
	$result = array();
	foreach ($xbase->columns as $column) {
		$result[$column->name] = $r->getString($column);
	}
	$result["deleted"] = $r->isDeleted();
	return $result;								// A tiny bugfix here, since in the original version the second argument "$record" was missing
}												//	V
function xbase_get_record($xbase_identifier=false,$record) { // - Gets a record from a dBase database
	if (!($xbase=&xbase_getInstance($xbase_identifier))) return false;
	$r =& $xbase->moveTo($record-1);
	$result = array();
	foreach ($xbase->columns as $column) {
		$result[] = $r->getString($column);
	}
	$result["deleted"] = $r->isDeleted();
	return $result;
}
function xbase_numfields($xbase_identifier=false) { // - Find out how many fields are in a dBase database 
	if (!($xbase=&xbase_getInstance($xbase_identifier))) return false;
	return $xbase->getColumnCount();
}
function xbase_numrecords($xbase_identifier=false) { // - Find out how many records are in a dBase database 
	if (!($xbase=&xbase_getInstance($xbase_identifier))) return false;
	return $xbase->getRecordCount();
}
function xbase_open($filename,$flags=0) { // - Opens a dBase database - flags : Typically 0 means read-only, 1 means write-only, and 2 means read and write
	if ($flags==0) {
		$xbase =& new XBaseTable($filename);
		if (!$xbase->open()) return false;
	} else {
		$xbase =& new XBaseWritableTable($filename);
		if (!$xbase->openWrite()) return false;
	}
	return xbase_addInstance($xbase);
}
function xbase_pack($xbase_identifier=false) { // - Packs a dBase database
	if (!($xbase=&xbase_getInstance($xbase_identifier))) return false;
	$xbase->pack();
}
function xbase_replace_record($xbase_identifier=false,$record,$record_number) { // - Replace a record in a dBase database
	if (!($xbase=&xbase_getInstance($xbase_identifier))) return false;
	$r =& $xbase->moveTo($record_number-1);
	foreach ($record as $i=>$v) {
		if (is_object($i))
			$r->setString($i,$v);
		else if (is_numeric($i)) 
			$r->setStringByIndex($i,$v);
		else 
			$r->setStringByName($i,$v);
	}
	$xbase->writeRecord();
}

/**
*	private
*/
$xbase_instances = array();
function &xbase_getInstance($i=NULL) {
	global $xbase_instances;
	if (sizeof($xbase_instances)==0) trigger_error ("No xbases available", E_USER_ERROR);
	if (is_null($i)) {
		$result =& current($xbase_instances);
	} else {
		if (!@$xbase_instances[$i]) trigger_error ($i." is an invalid xbase identifier", E_USER_ERROR);
		$result =& $xbase_instances[$i];
	}
	return $result;
}
function xbase_addInstance(&$i) {
	global $xbase_instances;
	$result = sizeof($xbase_instances);
	$xbase_instances[$result]=&$i;
	return $result;
}


?>