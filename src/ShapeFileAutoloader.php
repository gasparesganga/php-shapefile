<?php
/***************************************************************************************************
ShapeFile - PHP library to read any ESRI Shapefile and its associated DBF into a PHP Array or WKT
    Author          : Gaspare Sganga
    Version         : 2.2.0
    License         : MIT
    Documentation   : http://gasparesganga.com/labs/php-shapefile
****************************************************************************************************/

namespace ShapeFile;

class ShapeFileAutoloader
{
    public static function register()
    {
        spl_autoload_register('static::loadClass');
    }
    
    public static function loadClass($class)
    {
        $prefix     = __NAMESPACE__ . '\\';
        $base_dir   = __DIR__ . '/';
        $prefix_len = strlen($prefix);
        
        if (strncmp($prefix, $class, $prefix_len) !== 0) return;
        $file = $base_dir . str_replace('\\', '/', substr($class, $prefix_len)) . '.php';
        
        if (file_exists($file)) require($file);
    }
}
