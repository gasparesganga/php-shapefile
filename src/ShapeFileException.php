<?php
/***************************************************************************************************
ShapeFile - PHP library to read any ESRI Shapefile and its associated DBF into a PHP Array or WKT
    Author          : Gaspare Sganga
    Version         : 2.2.0
    License         : MIT
    Documentation   : http://gasparesganga.com/labs/php-shapefile
****************************************************************************************************/

namespace ShapeFile;

class ShapeFileException extends \Exception
{
    private $error_type;
    
    public function __construct($message, $code, $error_type, Exception $previous = null)
    {
        $this->error_type = $error_type;
        parent::__construct($message, $code, $previous);
    }
    
    public function getErrorType()
    {
        return $this->error_type;
    }
}
