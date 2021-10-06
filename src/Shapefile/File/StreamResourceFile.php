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

use Shapefile\Shapefile;
use Shapefile\ShapefileException;

/**
 * Default File implementation.
 * It allows reading/writing of files in binary mode.
 * It accepts both filepaths and stream resource handles.
 */
class StreamResourceFile implements File
{
    /////////////////////////////// PRIVATE VARIABLES ///////////////////////////////
    /**
     * @var resource    File resource handle.
     */
    private $handle = null;
    
    /**
     * @var bool        Flag to store whether file was passed as resource handle or not.
     */
    private $flag_resource = false;
    
    
    
    /////////////////////////////// PUBLIC ///////////////////////////////
    /**
     * Constructor.
     * Opens file with binary read or write access.
     *
     * @param   string|resource $file           Path to file or resource handle.
     * @param   bool            $write_access   Access type: false = read; true = write.
     */
    public function __construct($file, $write_access)
    {
        $this->flag_resource = is_resource($file);
        
        if ($this->flag_resource) {
            $this->handle = $file;
            if (get_resource_type($this->handle) !== 'stream' || !stream_get_meta_data($this->handle)['seekable']) {
                throw new ShapefileException(Shapefile::ERR_FILE_RESOURCE_NOT_VALID);
            }
            if ((!$write_access && !$this->isReadable()) || ($write_access && !$this->isWritable())) {
                throw new ShapefileException(Shapefile::ERR_FILE_PERMISSIONS);
            }
        } else {
            $this->handle = @fopen($file, $write_access ? 'c+b' : 'rb');
            if ($this->handle === false) {
                throw new ShapefileException(Shapefile::ERR_FILE_OPEN);
            }
        }
    }
    
    /**
     * Destructor.
     *
     * Closes file if it was NOT passed as resource handle.
     */
    public function __destruct()
    {
        if (!$this->flag_resource) {
            fclose($this->handle);
        }
    }
    
    
    /**
     * Gets canonicalized absolute pathname.
     */
    public function getFilepath()
    {
        return realpath(stream_get_meta_data($this->handle)['uri']);
    }
    
    
    public function isReadable()
    {
        return in_array(stream_get_meta_data($this->handle)['mode'], ['rb', 'r+b', 'w+b', 'x+b', 'c+b']);
    }
    
    public function isWritable()
    {
        return in_array(stream_get_meta_data($this->handle)['mode'], ['r+b', 'wb', 'w+b', 'xb', 'x+b', 'cb', 'c+b']);
    }
    
    
    public function truncate($size)
    {
        ftruncate($this->handle, $size);
    }
    
    public function getSize()
    {
        return fstat($this->handle)['size'];
    }
    
    
    public function getPointer()
    {
        return ftell($this->handle);
    }
    
    public function setPointer($position)
    {
        fseek($this->handle, $position, SEEK_SET);
    }
    
    public function resetPointer()
    {
        fseek($this->handle, 0, SEEK_END);
    }
    
    public function setOffset($offset)
    {
        fseek($this->handle, $offset, SEEK_CUR);
    }
    
    
    public function read($length)
    {
        return @fread($this->handle, $length);
    }
    
    public function write($data)
    {
        if (@fwrite($this->handle, $data) === false) {
            return false;
        }
        return true;
    }
}
