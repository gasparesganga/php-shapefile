<?php
/**
 * PHP Shapefile - PHP library to read and write ESRI Shapefiles, compatible with WKT and GeoJSON
 *  
 * @package Shapefile
 * @author  Gaspare Sganga
 * @version 3dev
 * @license MIT
 * @link    https://gasparesganga.com/labs/php-shapefile/
 */

namespace Shapefile;

/**
 * Exception throw by PHP Shapefile library.
 */
class ShapefileException extends \Exception
{    
    /**
     * @var string      Error type that raised the exception.
     */
    private $error_type;
    
    
    /**
     * Constructor
     *
     * @param   string  $error_type     Error type.
     * @param   string  $details        Optional information about the error.
     */
    public function __construct($error_type, $details = '')
    {
        $this->error_type = $error_type;
        
        $message = constant('Shapefile\Shapefile::' . $error_type . '_MESSAGE');
        if ($details !== '') {
            $message .= ': "' . $details . '"';
        }
        
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
}
