<?php
/**
* ----------------------------------------------------------------
*			XBase
*			Record.class.php	
* 
*  Developer        : Erwin Kooi
*  released at      : Nov 2005
*  last modified by : Erwin Kooi
*  date modified    : Jan 2005
*
*  You're free to use this code as long as you don't alter it
*  Copyright (c) 2005 Cyane Dynamic Web Solutions
*  Info? Mail to info@cyane.nl
* 
* --------------------------------------------------------------
*
* This class defines the data access functions to a DBF record
* Do not construct an instance yourself, generate records through the nextRecord function of XBaseTable
*
**/

define ("DBFFIELD_TYPE_MEMO","M");		// Memo type field.
define ("DBFFIELD_TYPE_CHAR","C");		// Character field.
define ("DBFFIELD_TYPE_NUMERIC","N");	// Numeric
define ("DBFFIELD_TYPE_FLOATING","F");	// Floating point
define ("DBFFIELD_TYPE_DATE","D");		// Date
define ("DBFFIELD_TYPE_LOGICAL","L");	// Logical - ? Y y N n T t F f (? when not initialized).
define ("DBFFIELD_TYPE_DATETIME","T");	// DateTime

define ("DBFFIELD_TYPE_INDEX","I");    // Index 
define ("DBFFIELD_IGNORE_0","0");		// ignore this field


class XBaseRecord {

    var $zerodate = 0x253d8c;
    var $table;
    var $choppedData;
    var $deleted;
    var $inserted;
    var $recordIndex;
    
    function XBaseRecord($table, $recordIndex, $rawData=false) {
        $this->table =& $table;
        $this->recordIndex=$recordIndex;
        $this->choppedData = array();
        if ($rawData && strlen($rawData)>0) {
	        $this->inserted=false;
        	$this->deleted=(ord($rawData[0])!="32");
        	foreach ($table->getColumns() as $column) {
            	$this->choppedData[]=substr($rawData,$column->getBytePos(),$column->getDataLength());
        	}
    	} else {
	    	$this->inserted=true;
	    	$this->deleted=false;
	    	foreach ($table->getColumns() as $column) {
		    	$this->choppedData[]=str_pad("", $column->getDataLength(),chr(0));
	    	}
    	}
    }
    function isDeleted() {
        return $this->deleted;
    }
    function getColumns() {
        return $this->table->getColumns();
    }
    function getColumnByName($name) {
        return $this->table->getColumnByName($name);
    }
    function getColumn($index) {
        return $this->table->getColumn($index);
    }
    function getColumnIndex($name) {
        return $this->table->getColumnIndex($name);
    }
    function getRecordIndex() {
        return $this->recordIndex;
    }

    /**
     * -------------------------------------------------------------------------
     * Get data functions
     * -------------------------------------------------------------------------
     */
    function getStringByName($columnName) {
        return $this->getString($this->table->getColumnByName($columnName));
    }
    function getStringByIndex($columnIndex) {
        return $this->getString($this->table->getColumn($columnIndex));
    }
    function getString($columnObj) {
        if ($columnObj->getType()==DBFFIELD_TYPE_CHAR) {
            return $this->forceGetString($columnObj);
        } else {
            $result = $this->getObject($columnObj);
            if ($result && ($columnObj->getType()==DBFFIELD_TYPE_DATETIME || $columnObj->getType()==DBFFIELD_TYPE_DATE)) return @date("r",$result);
            if ($columnObj->getType()==DBFFIELD_TYPE_LOGICAL) return $result?"1":"0";
            return $result;
        }
    }
    function forceGetString($columnObj) {
        if (ord($this->choppedData[$columnObj->getColIndex()][0])=="0") return false;
        return trim($this->choppedData[$columnObj->getColIndex()]);
    }
    function getObjectByName($columnName) {
        return $this->getObject($this->table->getColumnByName($columnName));
    }
    function getObjectByIndex($columnIndex) {
        return $this->getObject($this->table->getColumn($columnIndex));
    }
    function getObject($columnObj) {
        switch ($columnObj->getType()) {
            case DBFFIELD_TYPE_CHAR : return $this->getString($columnObj);
            case DBFFIELD_TYPE_DATE : return $this->getDate($columnObj);
            case DBFFIELD_TYPE_DATETIME : return $this->getDateTime($columnObj);
            case DBFFIELD_TYPE_FLOATING : return $this->getFloat($columnObj);
            case DBFFIELD_TYPE_LOGICAL : return $this->getBoolean($columnObj);
            case DBFFIELD_TYPE_MEMO : return $this->getMemo($columnObj);
            case DBFFIELD_TYPE_NUMERIC : return $this->getInt($columnObj);
            case DBFFIELD_TYPE_INDEX : return $this->getIndex($columnObj); 
            case DBFFIELD_IGNORE_0 : return false;
        }
        trigger_error ("cannot handle datatype".$columnObj->getType(), E_USER_ERROR);
    }
    function getDate($columnObj) {
	    if ($columnObj->getType()!=DBFFIELD_TYPE_DATE) trigger_error ($columnObj->getName()." is not a Date column", E_USER_ERROR);
        $s = $this->forceGetString($columnObj);
        if (!$s) return false;
        return strtotime($s);
    }
    function getDateTime($columnObj) {
        if ($columnObj->getType()!=DBFFIELD_TYPE_DATETIME) trigger_error ($columnObj->getName()." is not a DateTime column", E_USER_ERROR);
        $raw =  $this->choppedData[$columnObj->getColIndex()];
        $buf = unpack("i",substr($raw,0,4));
        $intdate = $buf[1];
        $buf = unpack("i",substr($raw,4,4));
        $inttime = $buf[1];

        if ($intdate==0 && $inttime==0) return false;

        $longdate = ($intdate-$this->zerodate)*86400;
        return $longdate+$inttime;
    }
    function getBoolean($columnObj) {
        if ($columnObj->getType()!=DBFFIELD_TYPE_LOGICAL) trigger_error ($columnObj->getName()." is not a DateTime column", E_USER_ERROR);
        $s = $this->forceGetString($columnObj);
        if (!$s) return false;
        switch (strtoupper($s[0])) {
            case 'T':
            case 'Y':
            case 'J':
            case '1':
                return true;

            default: return false;
        }
    }
    function getMemo($columnObj) {
	    if ($columnObj->getType()!=DBFFIELD_TYPE_MEMO) trigger_error ($columnObj->getName()." is not a Memo column", E_USER_ERROR);
        return $this->forceGetString($columnObj);
    }
    function getFloat($columnObj) {
	    if ($columnObj->getType()!=DBFFIELD_TYPE_FLOATING) trigger_error ($columnObj->getName()." is not a Float column", E_USER_ERROR);
        $s = $this->forceGetString($columnObj);
        if (!$s) return false;
        $s = str_replace(",",".",$s);
        return floatval($s);
    }
    function getInt($columnObj) {
	    if ($columnObj->getType()!=DBFFIELD_TYPE_NUMERIC) trigger_error ($columnObj->getName()." is not a Number column", E_USER_ERROR);
        $s = $this->forceGetString($columnObj);
        if (!$s) return false;
        $s = str_replace(",",".",$s);
        return intval($s);
    }
	function getIndex($columnObj) {
		if ($columnObj->getType()!=DBFFIELD_TYPE_INDEX) trigger_error ($columnObj->getName()." is not an Index column", E_USER_ERROR);
		$s = $this->choppedData[$columnObj->getColIndex()];
		if (!$s) return false;
		
		$ret = ord($s[0]);
		for ($i = 1; $i < $columnObj->length; $i++) {
			$ret += $i * 256 * ord($s[$i]);
		}
		return $ret;   
	} 

    /**
     * -------------------------------------------------------------------------
 	 * Set data functions
     * -------------------------------------------------------------------------
     **/
	function copyFrom($record) {
		$this->choppedData = $record->choppedData;
	}
    function setDeleted($b) {
       	$this->deleted=$b;
    }
    function setStringByName($columnName,$value) {
        $this->setString($this->table->getColumnByName($columnName),$value);
    }
    function setStringByIndex($columnIndex,$value) {
        $this->setString($this->table->getColumn($columnIndex),$value);
    }
    function setString($columnObj,$value) {
        if ($columnObj->getType()==DBFFIELD_TYPE_CHAR) {
            $this->forceSetString($columnObj,$value);
        } else {
	        if ($columnObj->getType()==DBFFIELD_TYPE_DATETIME || $columnObj->getType()==DBFFIELD_TYPE_DATE) $value = strtotime($value);
            $this->setObject($columnObj,$value);
        }
    }
    function forceSetString($columnObj,$value) {
        $this->choppedData[$columnObj->getColIndex()] = str_pad(substr($value,0,$columnObj->getDataLength()),$columnObj->getDataLength()," ");
    }
    function setObjectByName($columnName,$value) {
        return $this->setObject($this->table->getColumnByName($columnName),$value);
    }
    function setObjectByIndex($columnIndex,$value) {
        return $this->setObject($this->table->getColumn($columnIndex),$value);
    }
    function setObject($columnObj,$value) {
        switch ($columnObj->getType()) {
            case DBFFIELD_TYPE_CHAR : $this->setString($columnObj,$value); return;
            case DBFFIELD_TYPE_DATE : $this->setDate($columnObj,$value); return;
            case DBFFIELD_TYPE_DATETIME : $this->setDateTime($columnObj,$value); return;
            case DBFFIELD_TYPE_FLOATING : $this->setFloat($columnObj,$value); return;
            case DBFFIELD_TYPE_LOGICAL : $this->setBoolean($columnObj,$value); return;
            case DBFFIELD_TYPE_MEMO : $this->setMemo($columnObj,$value); return;
            case DBFFIELD_TYPE_NUMERIC : $this->setInt($columnObj,$value); return;
            case DBFFIELD_IGNORE_0 : return;
        }
        trigger_error ("cannot handle datatype".$columnObj->getType(), E_USER_ERROR);
    }
    function setDate($columnObj,$value) {
	    if ($columnObj->getType()!=DBFFIELD_TYPE_DATE) trigger_error ($columnObj->getName()." is not a Date column", E_USER_ERROR);
        if (strlen($value)==0) {
	        $this->forceSetString($columnObj,"");
	        return;
        }
       	$this->forceSetString($columnObj,date("Ymd",$value));
    }
    function setDateTime($columnObj,$value) {
        if ($columnObj->getType()!=DBFFIELD_TYPE_DATETIME) trigger_error ($columnObj->getName()." is not a DateTime column", E_USER_ERROR);
        if (strlen($value)==0) {
	        $this->forceSetString($columnObj,"");
	        return;
        }
        $a = getdate($value);
        $d = $this->zerodate + (mktime(0,0,0,$a["mon"],$a["mday"],$a["year"]) / 86400);
        $d = pack("i",$d);
        $t = pack("i",mktime($a["hours"],$a["minutes"],$a["seconds"],0,0,0));
        $this->choppedData[$columnObj->getColIndex()] = $d.$t;
    }
    function setBoolean($columnObj,$value) {
        if ($columnObj->getType()!=DBFFIELD_TYPE_LOGICAL) trigger_error ($columnObj->getName()." is not a DateTime column", E_USER_ERROR);
        switch (strtoupper($value)) {
            case 'T':
            case 'Y':
            case 'J':
            case '1':
            case 'F':
            case 'N':
            case '0':
                $this->forceSetString($columnObj,$value);
                return;
            
            case true:
                $this->forceSetString($columnObj,"T");
                return;

            default: $this->forceSetString($columnObj,"F");
        }
    }
    function setMemo($columnObj,$value) {
	    if ($columnObj->getType()!=DBFFIELD_TYPE_MEMO) trigger_error ($columnObj->getName()." is not a Memo column", E_USER_ERROR);
        return $this->forceSetString($columnObj,$value);
    }
    function setFloat($columnObj,$value) {
	    if ($columnObj->getType()!=DBFFIELD_TYPE_FLOATING) trigger_error ($columnObj->getName()." is not a Float column", E_USER_ERROR);
        if (strlen($value)==0) {
	        $this->forceSetString($columnObj,"");
	        return;
        }
        $value = str_replace(",",".",$value);
        $s = $this->forceSetString($columnObj,$value);
    }
    function setInt($columnObj,$value) {
	    if ($columnObj->getType()!=DBFFIELD_TYPE_NUMERIC) trigger_error ($columnObj->getName()." is not a Number column", E_USER_ERROR);
        if (strlen($value)==0) {
	        $this->forceSetString($columnObj,"");
	        return;
        }
        $value = str_replace(",",".",$value);
        //$this->forceSetString($columnObj,intval($value));
        
        /**
        * suggestion from Sergiu Neamt: treat number values as decimals
        **/
        $this->forceSetString($columnObj,number_format($value, $columnObj->decimalCount));
    }
    /**
     * -------------------------------------------------------------------------
 	 * Protected
     * -------------------------------------------------------------------------
     **/

     function serializeRawData() {
	     return ($this->deleted?"*":" ").implode("",$this->choppedData);
     }
}