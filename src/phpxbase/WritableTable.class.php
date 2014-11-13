<?php
/**
* ----------------------------------------------------------------
*			XBase
*			WritableTable.class.php	
* 
*  Developer        : Erwin Kooi
*  released at      : Jan 2005
*  last modified by : Erwin Kooi
*  date modified    : Jan 2006
*
*  You're free to use this code as long as you don't alter it
*  Copyright (c) 2005 Cyane Dynamic Web Solutions
*  Info? Mail to info@cyane.nl
* 
* --------------------------------------------------------------
*
* This class extends the main entry to a DBF table file, with writing abilities

*
**/

class XBaseWritableTable extends XBaseTable {
	
	/* static */
	function cloneFrom($table) {
		$result =& new XBaseWritableTable($table->name);
	    $result->version=$table->version;
	    $result->modifyDate=$table->modifyDate;
	    $result->recordCount=0;
	    $result->recordByteLength=$table->recordByteLength;
	    $result->inTransaction=$table->inTransaction;
	    $result->encrypted=$table->encrypted;
	    $result->mdxFlag=$table->mdxFlag;
	    $result->languageCode=$table->languageCode;
	    $result->columns=$table->columns;
	    $result->columnNames=$table->columnNames;
	    $result->headerLength=$table->headerLength;
	    $result->backlist=$table->backlist;
	    $result->foxpro=$table->foxpro;
	    return $result;
	}

	/* static */
	function create($filename,$fields) {
		if (!$fields || !is_array($fields)) trigger_error ("cannot create xbase with no fields", E_USER_ERROR);
		$recordByteLength=1;
		$columns=array();
		$columnNames=array();
		$i=0;
		foreach ($fields as $field) {
			if (!$field || !is_array($field) || sizeof($field)<2) trigger_error ("fields argument error, must be array of arrays", E_USER_ERROR);
			$column =& new XBaseColumn($field[0],$field[1],0,@$field[2],@$field[3],0,0,0,0,0,0,$i,$recordByteLength);
			$recordByteLength += $column->getDataLength();
			$columnNames[$i]=$field[0];
			$columns[$i]=$column;
			$i++;
		}
		
		$result =& new XBaseWritableTable($filename);
	    $result->version=131;
	    $result->modifyDate=time();
	    $result->recordCount=0;
	    $result->recordByteLength=$recordByteLength;
	    $result->inTransaction=0;
	    $result->encrypted=false;
	    $result->mdxFlag=chr(0);
	    $result->languageCode=chr(0);
	    $result->columns=$columns;
	    $result->columnNames=$columnNames;
	    $result->backlist="";
	    $result->foxpro=false;
	    if ($result->openWrite($filename,true)) return $result;
	    return false;
	}

    function openWrite($filename=false,$overwrite=false) {
	    if (!$filename) $filename = $this->name;
	    if (file_exists($filename) && !$overwrite) {
		    if ($this->fp = fopen($filename,"r+")) $this->readHeader();
	    } else {
		    if ($this->fp = fopen($filename,"w+")) $this->writeHeader();
    	}
    	return $this->fp!=false;
    }
    
    function writeHeader() {
	    $this->headerLength=($this->foxpro?296:33) + ($this->getColumnCount()*32);
	    fseek($this->fp,0);
	    $this->writeChar($this->version);
	    $this->write3ByteDate(time());
	    $this->writeInt($this->recordCount);
	    $this->writeShort($this->headerLength);
	    $this->writeShort($this->recordByteLength);
	    $this->writeBytes(str_pad("", 2,chr(0)));
	    $this->writeByte(chr($this->inTransaction?1:0));
	    $this->writeByte(chr($this->encrypted?1:0));
	    $this->writeBytes(str_pad("", 4,chr(0)));
	    $this->writeBytes(str_pad("", 8,chr(0)));
	    $this->writeByte($this->mdxFlag);
	    $this->writeByte($this->languageCode);
	    $this->writeBytes(str_pad("", 2,chr(0)));
	    
        foreach ($this->columns as $column) {
            $this->writeString(str_pad(substr($column->rawname,0,11), 11,chr(0)));
            $this->writeByte($column->type);
            $this->writeInt($column->memAddress);
            $this->writeChar($column->getDataLength());
            $this->writeChar($column->decimalCount);
            $this->writeBytes(str_pad("", 2,chr(0)));
            $this->writeChar($column->workAreaID);
            $this->writeBytes(str_pad("", 2,chr(0)));
            $this->writeByte(chr($column->setFields?1:0));
            $this->writeBytes(str_pad("", 7,chr(0)));
            $this->writeByte(chr($column->indexed?1:0));
        }

        if ($this->foxpro) {
            $this->writeBytes(str_pad($this->backlist, 263," "));
        }
        $this->writeChar(0x0d);
	}
	function &appendRecord() {
		$this->record =& new XBaseRecord($this,$this->recordCount);
		$this->recordCount+=1;
		return $this->record;
	}
	function writeRecord() {
		fseek($this->fp,$this->headerLength+($this->record->recordIndex*$this->recordByteLength));
		$data =& $this->record->serializeRawData();
		fwrite($this->fp,$data);
		if ($this->record->inserted) $this->writeHeader();
		flush($this->fp);
	}
	function deleteRecord() {
		$this->record->deleted=true;
		fseek($this->fp,$this->headerLength+($this->record->recordIndex*$this->recordByteLength));
		fwrite($this->fp,"!");
		flush($this->fp);
	}
	function undeleteRecord() {
		$this->record->deleted=false;
		fseek($this->fp,$this->headerLength+($this->record->recordIndex*$this->recordByteLength));
		fwrite($this->fp," ");
		flush($this->fp);
	}
	function pack() {
		$newRecordCount = 0;
		$newFilepos = $this->headerLength;
		for ($i=0;$i<$this->getRecordCount();$i++) {
			$r =& $this->moveTo($i);
			if ($r->isDeleted()) continue;
			$r->recordIndex = $newRecordCount++;
			$this->writeRecord();
		}
		$this->recordCount = $newRecordCount;
		$this->writeHeader();
		ftruncate($this->fp,$this->headerLength+($this->recordCount*$this->recordByteLength));
	}

    /**
     * -------------------------------------------------------------------------
     * private functions
     * -------------------------------------------------------------------------
     */
     
    function writeBytes($buf) {
	    return fwrite($this->fp,$buf);
    }
    function writeByte($b)  {
        return fwrite($this->fp,$b);
    }
    function writeString($s) {
        return $this->writeBytes($s);
    }
    function writeChar($c) {
	    $buf = pack("C",$c);
	    return $this->writeBytes($buf);
    }
    function writeShort($s) {
	    $buf = pack("S",$s);
	    return $this->writeBytes($buf);
    }
    function writeInt($i) {
	    $buf = pack("I",$i);
	    return $this->writeBytes($buf);
    }
    function writeLong($l) {
	    $buf = pack("L",$l);
	    return $this->writeBytes($buf);
    }
    function write3ByteDate($d) {
	    $t = getdate($d);
	    return $this->writeChar($t["year"] % 1000) + $this->writeChar($t["mon"]) + $this->writeChar($t["mday"]);
    }
    function write4ByteDate($d) {
	    $t = getdate($d);
	    return $this->writeShort($t["year"]) + $this->writeChar($t["mon"]) + $this->writeChar($t["mday"]);
    }
}