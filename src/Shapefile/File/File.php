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

namespace Shapefile\File;

/**
 * Interface to interact with files in binary mode.
 */
interface File
{
    /**
     * Returns true if file is readable.
     *
     * @return  bool
     */
    public function isReadable();
    
    
    /**
     * Returns true if file is writable.
     *
     * @return  bool
     */
    public function isWritable();
    
    
    /**
     * Truncates file to given length.
     *
     * @param   int     $size   Size to truncate to.
     *
     * @return  void
     */
    public function truncate($size);
    
    
    /**
     * Gets file size in bytes.
     *
     * @return  int
     */
    public function getSize();
    
    
    /**
     * Gets file current pointer position.
     *
     * @return  int
     */
    public function getPointer();
    
    
    /**
     * Sets file pointer to specified position.
     *
     * @param   int     $position   The position to set the pointer to.
     *
     * @return  void
     */
    public function setPointer($position);
    
    
    /**
     * Resets file pointer position to its end.
     *
     * @return  void.
     */
    public function resetPointer();
    
    
    /**
     * Increases file pointer position of specified offset.
     *
     * @param   int     $offset     The offset to move the pointer for.
     *
     * @return  void
     */
    public function setOffset($offset);
    
    
    /**
     * Reads raw binary string packed data from file.
     *
     * @param   int     $length     Number of bytes to read.
     *
     * @return  string|false    Returns binary string packed data, or false on failure.
     */
    public function read($length);
    
    
    /**
     * Writes raw binary string packed data to file.
     *
     * @param   string  $data       Binary string packed data to write.
     *
     * @return  bool        Returns true on success, or false on failure.
     */
    public function write($data);
}
