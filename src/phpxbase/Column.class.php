<?php
/**
* ----------------------------------------------------------------
*			XBase
*			XBaseColumn.class.php	
* 
*  Developer        : Erwin Kooi
*  released at      : Nov 2005
*  last modified by : Erwin Kooi
*  date modified    : Jan 2006
*
*  You're free to use this code as long as you don't alter it
*  Copyright (c) 2005 Cyane Dynamic Web Solutions
*  Info? Mail to info@cyane.nl
* 
* --------------------------------------------------------------
*
* This class represents a DBF column
* Do not construct an instance yourself, it's useless that way.
*
**/
class XBaseColumn {

    var $name;
    var $rawname;
    var $type;
    var $memAddress;
    var $length;
    var $decimalCount;
    var $workAreaID;
    var $setFields;
    var $indexed;
    var $bytePos;
    var $colIndex;

    function XBaseColumn(
        $name,
        $type,
        $memAddress,
        $length,
        $decimalCount,
        $reserved1,
        $workAreaID,
        $reserved2,
        $setFields,
        $reserved3,
        $indexed,
        $colIndex,
        $bytePos
    ) {
        $this->rawname=$name;
        $this->name=strpos($name,0x00)!==false?substr($name,0,strpos($name,0x00)):$name;
        $this->type=$type;
        $this->memAddress=$memAddress;
        $this->length=$length;
        $this->decimalCount=$decimalCount;
        $this->workAreaID=$workAreaID;
        $this->setFields=$setFields;
        $this->indexed=$indexed;
        $this->bytePos=$bytePos;
        $this->colIndex=$colIndex;
    }
    function getDecimalCount() {
        return $this->decimalCount;
    }
    function isIndexed() {
        return $this->indexed;
    }
    function getLength() {
        return $this->length;
    }
    function getDataLength() {
	    switch ($this->type) {
            case DBFFIELD_TYPE_DATE : return 8;
            case DBFFIELD_TYPE_DATETIME : return 8;
            case DBFFIELD_TYPE_LOGICAL : return 1;
            case DBFFIELD_TYPE_MEMO : return 10;
            default : return $this->length;
	    }
    }
    function getMemAddress() {
        return $this->memAddress;
    }
    function getName() {
        return $this->name;
    }
    function isSetFields() {
        return $this->setFields;
    }
    function getType() {
        return $this->type;
    }
    function getWorkAreaID() {
        return $this->workAreaID;
    }
    function toString() {
        return $this->name;
    }
    function getBytePos() {
        return $this->bytePos;
    }
    function getRawname() {
        return $this->rawname;
    }
    function getColIndex() {
        return $this->colIndex;
    }
}