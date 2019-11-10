<?php
/**
 * PHP Shapefile - PHP library to read and write ESRI Shapefiles, compatible with WKT and GeoJSON
 * 
 * @package Shapefile
 * @author  Gaspare Sganga
 * @version 3.1.1
 * @license MIT
 * @link    https://gasparesganga.com/labs/php-shapefile/
 */

namespace Shapefile;

/**
 * Static autoloader class. It only exposes public static method register().
 */
class ShapefileAutoloader
{
    /**
     * Register the actual autoloader.
     */
    public static function register()
    {
        spl_autoload_register(function ($class) {
            $prefix     = __NAMESPACE__ . '\\';
            $base_dir   = __DIR__ . '/';
            $prefix_len = strlen($prefix);
            
            if (strncmp($prefix, $class, $prefix_len) !== 0) {
                return;
            }
            $file = $base_dir . str_replace('\\', '/', substr($class, $prefix_len)) . '.php';
            
            if (file_exists($file)) {
                require($file);
            }
        });
    }
    
    
    /**
     * Private constructor, no instances of this class allowed.
     */
    private function __construct()
    {}
}
