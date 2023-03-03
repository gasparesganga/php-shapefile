<?php

/**
 * PHP Shapefile - PHP library to read and write ESRI Shapefiles, compatible with WKT and GeoJSON
 *
 * @package Shapefile
 * @author  Gaspare Sganga
 * @version 3.5.0dev
 * @license MIT
 * @link    https://gasparesganga.com/labs/php-shapefile/
 */

namespace Shapefile;

/**
 * Exception thrown by this library.
 */
class ShapefileException extends \Exception
{
    /**
     * @var string      Error type that raised the exception.
     */
    private $error_type;
    
    /**
     * @var string      Additional information about the error.
     */
    private $details;

    /**
     * @var string The field where the error was raised if appropriate
     */
    private string $field = '';
    
    
    /**
     * Constructor
     *
     * @param   string  $error_type     Error type.
     * @param   string  $details        Optional information about the error.
     */
    public function __construct($error_type, $details = '', $field = '')
    {
        $this->error_type   = $error_type;
        $this->details      = $details;
        $this->field        = $field;
        
        $message = constant('Shapefile\Shapefile::' . $error_type . '_MESSAGE');
        parent::__construct($message, 0, null);
    }
    
    /**
     * Gets internal error type.
     *
     * @return  string
     */
    public function getErrorType()
    {
        return $this->error_type;
    }
    
    /**
     * Gets error details.
     *
     * @return  string
     */
    public function getDetails()
    {
        return $this->details;
    }

    /**
     * Gets field name if appropriate, empty string otherwise
     *
     * @return  string
     */
    public function getField()
    {
        return $this->field;
    }
}
