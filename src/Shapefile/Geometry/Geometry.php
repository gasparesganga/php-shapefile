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

namespace Shapefile\Geometry;

use Shapefile\Shapefile;
use Shapefile\ShapefileException;

/**
 * Abstract base class for all geometries.
 * It defines some common public methods and some helper protected functions.
 */
abstract class Geometry
{
    /**
     * @var array|null      Custom bounding box set with setCustomBoundingBox() method.
     */
    private $custom_bounding_box = null;
    
    /**
     * @var array   Data of the Geometry.
     */
    private $data = [];
    
    /**
     * @var bool    Flag representing whether the Geometry is empty.
     */
    private $flag_empty = true;
    
    /**
     * @var bool    Flag representing whether the Geometry has Z dimension.
     */
    private $flag_z = false;
    
    /**
     * @var bool    Flag representing whether the Geometry has M dimension.
     */
    private $flag_m = false;
    
    /**
     * @var bool    Flag representing whether the DBF record is deleted.
     */
    private $flag_deleted = false;
    
    
    
    /////////////////////////////// ABSTRACT ///////////////////////////////
    /**
     * Initialize the Geometry with a structured array.
     *
     * @param   array   $array      Array structured according to Geometry type.
     */
    abstract public function initFromArray($array);
    
    /**
     * Initialize the Geometry with WKT.
     *
     * @param   string  $wkt        WKT string.
     */
    abstract public function initFromWKT($wkt);
    
    /**
     * Initialize the Geometry with GeoJSON.
     *
     * @param   string  $geojson    GeoJSON string.
     */
    abstract public function initFromGeoJSON($geojson);
    
    
    /**
     * Gets the Geometry as a structured array.
     *
     * @return  array
     */
    abstract public function getArray();
    
    /**
     * Gets the Geometry as WKT.
     *
     * @return  string
     */
    abstract public function getWKT();
    
    /**
     * Gets the Geometry as GeoJSON.
     *
     * @param   bool    $flag_bbox      If true include the bounding box in the GeoJSON output.
     * @param   bool    $flag_feature   If true output a GeoJSON Feature with all the data.
     *
     * @return  string
     */
    abstract public function getGeoJSON($flag_bbox, $flag_feature);
    
    /**
     * Gets Geometry bounding box.
     * If a custom one is defined, it will be returned instead of a computed one.
     * 
     * @return  array   Associative array with the xmin, xmax, ymin, ymax and optional zmin, zmax, mmin, mmax values.
     */
    abstract public function getBoundingBox();
    
    /**
     * Gets the Shape base type of the Geometry.
     * This is not intended for users, but Shapefile requires it for internal mechanisms.
     *
     * @internal
     * 
     * @return  integer
     */
    abstract public function getSHPBasetype();
    
    
    /**
     * Gets the WKT base type of the Geometry.
     * 
     * @return  string
     */
    abstract protected function getWKTBasetype();
    
    /**
     * Gets the GeoJSON base type of the Geometry.
     * 
     * @return  string
     */
    abstract protected function getGeoJSONBasetype();
    
    
    
    /////////////////////////////// PUBLIC ///////////////////////////////
    /**
     * Gets the state of the Empty flag.
     * 
     * @return  bool
     */
    public function isEmpty()
    {
        return $this->flag_empty;
    }
    
    /**
     * Gets the state of the Z flag.
     * 
     * @return  bool
     */
    public function isZ()
    {
        return $this->flag_z;
    }
    
    /**
     * Gets the state of the M flag.
     * 
     * @return  bool
     */
    public function isM()
    {
        return $this->flag_m;
    }
    
    /**
     * Gets the state of the Deleted flag.
     * 
     * @return  bool
     */
    public function isDeleted()
    {
        return $this->flag_deleted;
    }
    
    /**
     * Sets the state of the Deleted flag.
     * 
     * @param   bool    $value
     */
    public function setFlagDeleted($value)
    {
        $this->flag_deleted = $value;
    }
    
    
    /**
     * Sets a custom bounding box for the Geometry.
     * No check is carried out except a formal compliance of dimensions.
     *
     * @param   array   $bounding_box    Associative array with the xmin, xmax, ymin, ymax and optional zmin, zmax, mmin, mmax values.
     */
    public function setCustomBoundingBox($bounding_box)
    {
        $bounding_box = array_intersect_key($bounding_box, array_flip(['xmin', 'xmax', 'ymin', 'ymax', 'zmin', 'zmax', 'mmin', 'mmax']));
        if (
                $this->isEmpty()
            ||  !isset($bounding_box['xmin'], $bounding_box['xmax'], $bounding_box['ymin'], $bounding_box['ymax'])
            ||  (($this->isZ() && !isset($bounding_box['zmin'], $bounding_box['zmax'])) || (!$this->isZ() && (isset($bounding_box['zmin']) || isset($bounding_box['zmax']))))
            ||  (($this->isM() && !isset($bounding_box['mmin'], $bounding_box['mmax'])) || (!$this->isM() && (isset($bounding_box['mmin']) || isset($bounding_box['mmax']))))
        ) {
            throw new ShapefileException(Shapefile::ERR_GEOM_MISMATCHED_BBOX);
        }
        $this->custom_bounding_box = $bounding_box;
    }
    
    /**
     * Resets custom bounding box for the Geometry.
     * It will cause getBoundingBox() method to return a normally computed bbox instead of a custom one.
     */
    public function resetCustomBoundingBox()
    {
        $this->custom_bounding_box = null;
    }
    
    
    /**
     * Gets data value for speficied field name.
     *
     * @param   string  $fieldname  Name of the field.
     *
     * @return  mixed
     */
    public function getData($fieldname)
    {
        if (!isset($this->data[$fieldname])) {
            throw new ShapefileException(Shapefile::ERR_INPUT_FIELD_NOT_FOUND, $fieldname);
        }
        return $this->data[$fieldname];
    }
    
    /**
     * Sets data value for speficied field name.
     *
     * @param   string  $fieldname  Name of the field.
     * @param   mixed   $value      Value to assign to the field.
     */
    public function setData($fieldname, $value)
    {
        $this->data[$fieldname] = $value;
    }
    
    /**
     * Gets an array of defined data.
     *
     * @return  array
     */
    public function getDataArray()
    {
        return $this->data;
    }
    
    /**
     * Sets an array of data.
     *
     * @param   array   $data       Associative array of values.
     */
    public function setDataArray($data)
    {
        foreach ($data as $fieldname => $value) {
            $this->data[$fieldname] = $value;
        }
    }
    
    
    
    /////////////////////////////// PROTECTED ///////////////////////////////
    /**
     * Sets the state of the Empty flag.
     * 
     * @param   bool    $value
     */
    protected function setFlagEmpty($value)
    {
        $this->flag_empty = $value;
    }
    
    /**
     * Sets the state of the Z flag.
     * 
     * @param   bool    $value
     */
    protected function setFlagZ($value)
    {
        $this->flag_z = $value;
    }
    
    /**
     * Sets the state of the M flag.
     * 
     * @param   bool    $value
     */
    protected function setFlagM($value)
    {
        $this->flag_m = $value;
    }
    
    
    /**
     * Checks if the Geometry has been initialized (it is not empty) and if YES throws and exception.
     */
    protected function checkInit()
    {
        if (!$this->isEmpty()) {
            throw new ShapefileException(Shapefile::ERR_GEOM_NOT_EMPTY);
        }
    }
    
    
    /**
     * Gets the custom bounding box.
     * 
     * @return  array
     */
    protected function getCustomBoundingBox()
    {
        return $this->custom_bounding_box;
    }
    
    
    /**
     * Sanitize WKT.
     * It attempts to sanitize user-provided WKT and throws an exception if it appears to be invalid.
     *
     * @param   string  $wkt    The WKT to sanitize.
     *
     * @return  string  Sanitized WKT.
     */
    protected function wktSanitize($wkt)
    {
        // Normalize whitespaces
        $wkt = strtoupper(preg_replace('/\s+/', ' ', trim($wkt)));
        // Normalize commas
        $wkt = str_replace(array(', ', ' ,'), ',', $wkt);
        // Check basetype
        if (substr($wkt, 0, strlen($this->getWKTBasetype())) != strtoupper($this->getWKTBasetype())) {
            throw new ShapefileException(Shapefile::ERR_INPUT_WKT_NOT_VALID);
        }
        return $wkt;
    }
    
    /**
     * Checks if WKT represents an empty Geometry.
     *
     * @param   string  $wkt
     *
     * @return  bool
     */
    protected function wktIsEmpty($wkt)
    {
        return substr($wkt, -5) == 'EMPTY';
    }
    
    /**
     * Checks if WKT represents a Geometry that has a Z dimension.
     *
     * @param   string  $wkt    The whole sanitized WKT.
     *
     * @return  bool
     */
    protected function wktIsZ($wkt)
    {
        return strpos(trim(substr($wkt, strlen($this->getWKTBasetype()), 3)), 'Z') !== false;
    }
    
    /**
     * Checks if WKT represents a Geometry that has a M dimension.
     *
     * @param   string  $wkt    The whole sanitized WKT.
     *
     * @return  bool
     */
    protected function wktIsM($wkt)
    {
        return strpos(trim(substr($wkt, strlen($this->getWKTBasetype()), 3)), 'M') !== false;
    }
    
    /**
     * Extracts data from WKT.
     *
     * @param   string  $wkt    The whole sanitized WKT.
     *
     * @return  string
     */
    protected function wktExtractData($wkt)
    {
        if ($this->wktIsEmpty($wkt)) {
            return null;
        }
        $begin = strpos($wkt, '(');
        if ($begin === false) {
            throw new ShapefileException(ERR_INPUT_WKT_NOT_VALID);
        }
        $end = strrpos($wkt, ')');
        if ($end === false) {
            throw new ShapefileException(ERR_INPUT_WKT_NOT_VALID);
        }
        return trim(substr($wkt, $begin + 1, $end - $begin - 1));
    }
    
    /**
     * Parse a group of WKT coordinates into an associative array.
     * Refer to parseCoordinatesArray() method for output details.
     *
     * @param   string  $coordinates_string The WKT coordinates group.
     * @param   bool    $force_z            Flag to enforce the presence of Z dimension.
     * @param   bool    $force_m            Flag to enforce the presence of M dimension.
     *
     * @return  array
     */
    protected function wktParseCoordinates($coordinates_string, $force_z, $force_m)
    {
        return $this->parseCoordinatesArray(explode(' ', trim($coordinates_string)), $force_z, $force_m, Shapefile::ERR_INPUT_WKT_NOT_VALID);
    }
    
    /**
     * Returns an initialized WKT according to the Geometry properties.
     *
     * @return  string
     */
    protected function wktInitializeOutput()
    {
        $ret = $this->getWKTBasetype();
        if ($this->isEmpty()) {
            $ret .= ' EMPTY';
        } else {
            $ret .= ($this->isZ() ? 'Z' : '') . ($this->isM() ? 'M' : '');
        }
        return $ret;
    }
    
    
    /**
     * Return sanitized GeoJSON, keeping just the geometry part.
     * It attempts to sanitize user-provided GeoJSON and throws an exception if it appears to be invalid.
     *
     * @param   string  $geojson    The GeoJSON to sanitize.
     *
     * @return  string
     */
    protected function geojsonSanitize($geojson)
    {
        $geojson = json_decode($geojson, true);
        
        // If it is null it means "an empty Geometry"
        if ($geojson === null) {
            return null;
        }
        // If it is a Feature just keep the Geometry part
        if (isset($geojson['geometry'])) {
            $geojson = $geojson['geometry'];
        }
        // Check if "type" and "coordinates" are defined
        if (!isset($geojson['type'], $geojson['coordinates'])) {
            throw new ShapefileException(Shapefile::ERR_INPUT_GEOJSON_NOT_VALID);
        }
        // Check if "type" is consistent with current Geometry
        if (substr(strtoupper(trim($geojson['type'])), 0, strlen($this->getGeoJSONBasetype())) != strtoupper($this->getGeoJSONBasetype())) {
            throw new ShapefileException(Shapefile::ERR_INPUT_GEOJSON_NOT_VALID);
        }
        
        return $geojson;
    }
    
    /**
     * Checks if GeoJSON type represents a Geometry that has a M dimension.
     *
     * @param   string  $type   The type speficied in the GeoJSON.
     *
     * @return  bool
     */
    protected function geojsonIsM($type)
    {
        return substr($type, -1) == 'M';
    }
    
    /**
     * Parse an array of GeoJSON coordinates into an associative array.
     * Refer to parseCoordinatesArray() method for output details.
     *
     * @param   string  $coordinates_array  GeoJSON coordinates array.
     * @param   bool    $force_m            Flag to enforce the presence of M dimension.
     *
     * @return  array
     */
    protected function geojsonParseCoordinates($coordinates_array, $force_m)
    {
        return $this->parseCoordinatesArray($coordinates_array, false, $force_m, Shapefile::ERR_INPUT_GEOJSON_NOT_VALID);
    }
    
    /**
     * Builds valid GeoJSON starting from raw coordinates.
     *
     * @param   array   $coordinates    GeoJSON coordinates array.
     * @param   bool    $flag_bbox      If true include the bounding box in the GeoJSON output.
     * @param   bool    $flag_feature   If true output a GeoJSON Feature with all the data.
     *
     * @return  array
     */
    protected function geojsonPackOutput($coordinates, $flag_bbox, $flag_feature)
    {
        $ret = [];
        // Type
        $ret['type'] = $this->getGeoJSONBasetype() . ($this->isM() ? 'M' : '');
        // Bounding box
        if ($flag_bbox) {
            $ret['bbox'] = [];
            $bbox = $this->getBoundingBox();
            $ret['bbox'][] = $bbox['xmin'];
            $ret['bbox'][] = $bbox['ymin'];
            if ($this->isZ()) {
                $ret['bbox'][] = $bbox['zmin'];
            }
            if ($this->isM()) {
                $ret['bbox'][] = $bbox['mmin'];
            }
            $ret['bbox'][] = $bbox['xmax'];
            $ret['bbox'][] = $bbox['ymax'];
            if ($this->isZ()) {
                $ret['bbox'][] = $bbox['zmax'];
            }
            if ($this->isM()) {
                $ret['bbox'][] = $bbox['mmax'];
            }
        }
        // Coordinates
        $ret['coordinates'] = $coordinates;
        // Feature
        if ($flag_feature) {
            $ret = [
                'type'          => 'Feature',
                'geometry'      => $ret,
                'properties'    => $this->data,
            ];
        }
        
        return json_encode($ret);
    }
    
    
    /////////////////////////////// PRIVATE ///////////////////////////////
     /**
     * Parses an indexed array of coordinates and returns an associative one in the form of:
     *  [
     *      "x" => float
     *      "y" => float
     *      "z" => float|null
     *      "m" => float|null
     *  ]
     *
     * @param   float[] $coordinates    The indexed array of coordinates to parse.
     * @param   bool    $force_z        Flag to enforce the presence of Z dimension.
     * @param   bool    $force_m        Flag to enforce the presence of M dimension.
     * @param   integer $err_code       Error code to throw an exception in case of invalid input.
     *
     * @return  array
     */
    private function parseCoordinatesArray($coordinates, $force_z, $force_m, $err_code)
    {
        $count = count($coordinates);
        if (
            $count < 2                              || 
            (($force_z || $force_m) && $count < 3)  || 
            ($force_z && $force_m && $count < 4)    || 
            $count > 4
        ) {
            throw new ShapefileException($err_code);
        }
        
        $ret = [
            'x' => $coordinates[0],
            'y' => $coordinates[1],
            'z' => null,
            'm' => null,
        ];
        if ($count == 3) {
            if ($force_m) {
                $ret['m'] = $coordinates[2];
            } else {
                $ret['z'] = $coordinates[2];
            }
        }
        if ($count == 4) {
            $ret['z'] = $coordinates[2];
            $ret['m'] = $coordinates[3];
        }
        return $ret;
    }
    
}
