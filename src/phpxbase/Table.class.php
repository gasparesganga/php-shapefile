<?php
/**
* ----------------------------------------------------------------
*			XBase
*			Table.class.php	
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
* This class provides the main entry to a DBF table file.
* common usage:
* 1. construct an instance
* 	$table = new XBaseTable($name);
* where $name is the path to the the DBF file, or a stream like 'php://input'
*
* 2. open the file to read the header
*	$table->open();
*
* 3. iterate through the records
*	while ($record=$table->nextRecord()) { ... }
*
* 4. close the file
*	$table->close();
*
**/

class XBaseTable {

    var $name;
    var $fp;
    var $isStream;
    var $filePos=0;
    var $recordPos=-1;
    var $record;

    var $version;
    var $modifyDate;
    var $recordCount;
    var $recordByteLength;
    var $inTransaction;
    var $encrypted;
    var $mdxFlag;
    var $languageCode;
    var $columns;
    var $columnNames;
    var $headerLength;
    var $backlist;
    var $foxpro;
    var $deleteCount=0;

    function XBaseTable($name) {
        $this->name=$name;
    }
    
    function open() {
	    
	    $fn = $this->name;
	    $this->isStream=strpos($this->name,"://")!==false;
	    if (!$this->isStream) {
	    	if (!file_exists($fn)) $fn = $this->name.".DBF";
	    	if (!file_exists($fn)) $fn = $this->name.".dbf";
	    	if (!file_exists($fn)) $fn = $this->name.".Dbf";
	    	if (!file_exists($fn)) trigger_error ($this->name." cannot be found", E_USER_ERROR);
    	}
    	$this->name = $fn;
    	$this->fp = fopen($fn,"rb");
		$this->readHeader();
		return $this->fp!=false;
		
	}
	
	function readHeader() {

        $this->version = $this->readChar();
        $this->foxpro = $this->version==48 || $this->version==49 || $this->version==245 || $this->version==251;
        $this->modifyDate = $this->read3ByteDate();
        $this->recordCount = $this->readInt();
        $this->headerLength = $this->readShort();
        $this->recordByteLength = $this->readShort();
        $this->readBytes(2); //reserved
        $this->inTransaction = $this->readByte()!=0;
        $this->encrypted = $this->readByte()!=0;
        $this->readBytes(4); //Free record thread
        $this->readBytes(8); //Reserved for multi-user dBASE
        $this->mdxFlag = $this->readByte();
        $this->languageCode = $this->readByte();
        $this->readBytes(2); //reserved

        $fieldCount = ($this->headerLength - ($this->foxpro?296:33) ) / 32;
        
        /* some checking */
        if (!$this->isStream && $this->headerLength>filesize($this->name)) trigger_error ($this->name." is not DBF", E_USER_ERROR);
        if (!$this->isStream && $this->headerLength+($this->recordCount*$this->recordByteLength)-500>filesize($this->name)) trigger_error ($this->name." is not DBF", E_USER_ERROR);

        /* columns */
        $this->columnNames = array();
        $this->columns = array();
        $bytepos = 1;
        for ($i=0;$i<$fieldCount;$i++) {
            $column =& new XBaseColumn(
                $this->readString(11),	// name
                $this->readByte(),		// type
                $this->readInt(),		// memAddress
                $this->readChar(),		// length
                $this->readChar(),		// decimalCount
                $this->readBytes(2),	// reserved1
                $this->readChar(),		// workAreaID
                $this->readBytes(2),	// reserved2
                $this->readByte()!=0,	// setFields
                $this->readBytes(7),	// reserved3
                $this->readByte()!=0,	// indexed
                $i,						// colIndex
                $bytepos				// bytePos
            );
            $bytepos+=$column->getLength();
            $this->columnNames[$i] = $column->getName();
            $this->columns[$i] =& $column;
        }

        /**/
        if ($this->foxpro) {
            $this->backlist=$this->readBytes(263);
        }
        $b = $this->readByte();
        $this->recordPos=-1;
        $this->record=false;
        $this->deleteCount=0;
    }
    function isOpen() {
        return $this->fp?true:false;
    }
    function close() {
        fclose($this->fp);
    }
    function &nextRecord() {
	    if (!$this->isOpen()) $this->open();
        $valid=false;
        do {
            if ($this->recordPos+1>=$this->recordCount) return false;
            $this->recordPos++;
            $this->record =& new XBaseRecord($this,$this->recordPos,$this->readBytes($this->recordByteLength));
            if ($this->record->isDeleted()) {
                $this->deleteCount++;
            } else {
	            $valid=true;
            }
        } while (!$valid);
        return $this->record;
    }
    function &moveTo($index) {
	    $this->recordPos=$index;
	    if ($index<0) return;
	    fseek($this->fp,$this->headerLength+($index*$this->recordByteLength));
	    $this->record =& new XBaseRecord($this,$this->recordPos,$this->readBytes($this->recordByteLength));
        return $this->record;
    }
    function &getRecord() {
        return $this->record;
    }
    function getColumnNames() {
        return $this->columnNames;
    }
    function getColumns() {
        return $this->columns;
    }
    function &getColumn($index) {
        return $this->columns[$index];
    }
    function &getColumnByName($name) {
        foreach ($this->columnNames as $i=>$n) if (strtoupper($n) == strtoupper($name)) return $this->columns[$i];
        return false;
    }
    function getColumnIndex($name) {
        foreach ($this->columnNames as $i=>$n) if (strtoupper($n) == strtoupper($name)) return $i;
        return false;
    }
    function getColumnCount() {
        return sizeof($this->columns);
    }
    function getRecordCount() {
        return $this->recordCount;
    }
    function getRecordPos() {
        return $this->recordPos;
    }
    function getRecordByteLength() {
        return $this->recordByteLength;
    }
    function getName() {
        return $this->name;
    }
    function getDeleteCount() {
        return $this->deleteCount;
    }
    
    function toHTML($withHeader=true,$tableArgs="border='1'",$trArgs="",$tdArgs="",$thArgs="") {
	    $result = "<table $tableArgs >\n";
	    if ($withHeader) {
		    $result .= "<tr>\n";
		    foreach ($this->getColumns() as $i=>$c) {
			    $result .= "<th $thArgs >".$c->getName()."</th>\n";
		    }
		    $result .= "</tr>\n";
	    }
	    $this->moveTo(-1);
	    while ($r =& $this->nextRecord()) {
		    $result .= "<tr $trArgs >\n";
		    foreach ($this->getColumns() as $i=>$c) {
			    $result .= "<td $tdArgs >".$r->getString($c)."</td>\n";
		    }
		    $result .= "</tr>\n";
	    }
	    $result .= "</table>\n";
	    return $result;
    }

    function toXML() {
		$result = "<table ";
		$result.= "name='".$this->name."' ";
		$result.= "version='".$this->version."' ";
		$result.= "modifyDate='".$this->modifyDate."' ";
		$result.= "recordCount='".$this->recordCount."' ";
		$result.= "recordByteLength='".$this->recordByteLength."' ";
		$result.= "inTransaction='".$this->inTransaction."' ";
		$result.= "encrypted='".$this->encrypted."' ";
		$result.= "mdxFlag='".ord($this->mdxFlag)."' ";
		$result.= "languageCode='".ord($this->languageCode)."' ";
		$result.= "backlist='".base64_encode($this->backlist)."' ";
		$result.= "foxpro='".$this->foxpro."' ";
		$result.= "deleteCount='".$this->deleteCount."' ";
	    $result.= ">\n";
	    $result .= "<columns>\n";
	    foreach ($this->getColumns() as $i=>$c) {
		    $result .= "<column ";
			$result .= "name='".$c->name."' ";
			$result .= "type='".$c->type."' ";
			$result .= "length='".$c->length."' ";
			$result .= "decimalCount='".$c->decimalCount."' ";
			$result .= "bytePos='".$c->bytePos."' ";
			$result .= "colIndex='".$c->colIndex."' ";
			$result .= "/>\n";
	    }
	    $result .= "</columns>\n";
	    $result .= "<records>\n";
	    $this->moveTo(-1);
	    while ($r =& $this->nextRecord()) {
		    $result .= "<record>\n";
		    foreach ($this->getColumns() as $i=>$c) {
			    $result .= "<".$c->name.">".$r->getObject($c)."</".$c->name.">\n";
		    }
		    $result .= "</record>\n";
	    }
	    $result .= "</records>\n";
	    $result .= "</table>\n";
	    return $result;
    }
    
    /**
     * -------------------------------------------------------------------------
     * private functions
     * -------------------------------------------------------------------------
     */
    function readBytes($l) {
        $this->filePos+=$l;
        return fread($this->fp,$l);
    }
    function writeBytes($buf) {
	    return fwrite($this->fp,$buf);
    }
    function readByte()  {
        $this->filePos++;
        return fread($this->fp,1);
    }
    function writeByte($b)  {
        return fwrite($this->fp,$b);
    }
    function readString($l) {
        return $this->readBytes($l);
    }
    function writeString($s) {
        return $this->writeBytes($s);
    }
    function readChar() {
        $buf = unpack("C",$this->readBytes(1));
        return $buf[1];
    }
    function writeChar($c) {
	    $buf = pack("C",$c);
	    return $this->writeBytes($buf);
    }
    function readShort() {
        $buf = unpack("S",$this->readBytes(2));
        return $buf[1];
    }
    function writeShort($s) {
	    $buf = pack("S",$s);
	    return $this->writeBytes($buf);
    }
    function readInt() {
        $buf = unpack("I",$this->readBytes(4));
        return $buf[1];
    }
    function writeInt($i) {
	    $buf = pack("I",$i);
	    return $this->writeBytes($buf);
    }
    function readLong() {
        $buf = unpack("L",$this->readBytes(8));
        return $buf[1];
    }
    function writeLong($l) {
	    $buf = pack("L",$l);
	    return $this->writeBytes($buf);
    }
    function read3ByteDate() {
	    $y = unpack("c",$this->readByte());
	    $m = unpack("c",$this->readByte());
	    $d = unpack("c",$this->readByte());
        return mktime(0,0,0,$m[1],$d[1],$y[1]>70?1900+$y[1]:2000+$y[1]);
    }
    function write3ByteDate($d) {
	    $t = getdate($d);
	    return $this->writeChar($t["year"] % 1000) + $this->writeChar($t["mon"]) + $this->writeChar($t["mday"]);
    }
    function read4ByteDate() {
	    $y = readShort();
	    $m = unpack("c",$this->readByte());
	    $d = unpack("c",$this->readByte());
        return mktime(0,0,0,$m[1],$d[1],$y);
    }
    function write4ByteDate($d) {
	    $t = getdate($d);
	    return $this->writeShort($t["year"]) + $this->writeChar($t["mon"]) + $this->writeChar($t["mday"]);
    }
}