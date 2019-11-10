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
 * Point Geometry.
 *
 *  - Array: [
 *      [x] => float
 *      [y] => float
 *      [z] => float
 *      [m] => float/bool
 *  ]
 *  
 *  - WKT:
 *      POINT [Z][M] (x y z m)
 *
 *
 *  - GeoJSON:
 *      {
 *          "type": "Point" / "PointM"
 *          "coordinates": [x, y, z] / [x, y, m] / [x, y, z, m]
 *      }
 */
class Point extends Geometry
{
    /**
     * WKT and GeoJSON basetypes
     */
    const WKT_BASETYPE      = 'POINT';
    const GEOJSON_BASETYPE  = 'Point';
    
    /**
     * @var float   X coordinate
     */
    private $x = null;
    
    /**
     * @var float   Y coordinate
     */
    private $y = null;
    
    /**
     * @var float   Z coordinate
     */
    private $z = null;
    
    /**
     * @var float   M coordinate
     */
    private $m = null;
    
    
    /////////////////////////////// PUBLIC ///////////////////////////////
    /**
     * Constructor.
     * 
     * @param   float   $x      X coordinate
     * @param   float   $y      Y coordinate
     * @param   float   $z      Z coordinate
     * @param   float   $m      M coordinate
     */
    public function __construct($x = null, $y = null, $z = null, $m = null)
    {
        $this->init($x, $y, $z, $m);
    }
    
    public function initFromArray($array)
    {
        $this->checkInit();
        $this->init(
            isset($array['x']) ? $array['x'] : null,
            isset($array['y']) ? $array['y'] : null,
            isset($array['z']) ? $array['z'] : null,
            isset($array['m']) ? $array['m'] : null
        );
    }
    
    public function initFromWKT($wkt)
    {
        $this->checkInit();
        $wkt = $this->wktSanitize($wkt);
        if (!$this->wktIsEmpty($wkt)) {
            $coordinates = $this->wktParseCoordinates(
                $this->wktExtractData($wkt),
                $this->wktIsZ($wkt),
                $this->wktIsM($wkt)
            );
            $this->init($coordinates['x'], $coordinates['y'], $coordinates['z'], $coordinates['m']);
        }
    }
    
    public function initFromGeoJSON($geojson)
    {
        $this->checkInit();
        $geojson = $this->geojsonSanitize($geojson);
        if ($geojson !== null) {
            $coordinates = $this->geojsonParseCoordinates($geojson['coordinates'], $this->geojsonIsM($geojson['type']));
            $this->init($coordinates['x'], $coordinates['y'], $coordinates['z'], $coordinates['m']);
        }
    }
    
    
    public function getArray()
    {
        if ($this->isEmpty()) {
            return null;
        }
        
        $ret = [
            'x' => $this->x,
            'y' => $this->y,
        ];
        if ($this->isZ()) {
            $ret['z'] = $this->z;
        }
        if ($this->isM()) {
            $ret['m'] = $this->m;
        }
        return $ret;
    }
    
    public function getWKT()
    {
        $ret = $this->wktInitializeOutput();
        if (!$this->isEmpty()) {
            $ret .= '(' . implode(' ', $this->getRawArray()) . ')';
        }
        return $ret;
    }
    
    public function getGeoJSON($flag_bbox = false, $flag_feature = false)
    {
        if ($this->isEmpty()) {
            return 'null';
        }
        return $this->geojsonPackOutput($this->getRawArray(), $flag_bbox, $flag_feature);
    }
    
    public function getBoundingBox()
    {
        if ($this->isEmpty()) {
            return null;
        }
        $ret = $this->getCustomBoundingBox();
        if (!$ret) {
            $ret = [
                'xmin' => $this->x,
                'xmax' => $this->x,
                'ymin' => $this->y,
                'ymax' => $this->y,
            ];
            if ($this->isZ()) {
                $ret['zmin'] = $this->z;
                $ret['zmax'] = $this->z;
            }
            if ($this->isM()) {
                $ret['mmin'] = $this->m;
                $ret['mmax'] = $this->m;
            }
        }
        return $ret;
    }
    
    
    public function getSHPBasetype()
    {
        return Shapefile::SHAPE_TYPE_POINT;
    }
    
    
    /**
     * Gets X coordinate
     * 
     * @return  float
     */
    public function getX()
    {
        return $this->x;
    }
    
    /**
     * Gets Y coordinate
     * 
     * @return  float
     */
    public function getY()
    {
        return $this->y;
    }
    
    /**
     * Gets Z coordinate
     * 
     * @return  float
     */
    public function getZ()
    {
        return $this->z;
    }
    
    /**
     * Gets M coordinate
     * 
     * @return  float
     */
    public function getM()
    {
        return $this->m;
    }
    
    
    /**
     * @internal
     * 
     * Gets an indexed array of coordinates.
     * This is not actually for public use, rather it is used by other classes in the library.
     * 
     * @return  array
     */
    public function getRawArray()
    {
        $ret = [];
        if (!$this->isEmpty()) {
            $ret[] = $this->x;
            $ret[] = $this->y;
            if ($this->isZ()) {
                $ret[] = $this->z;
            }
            if ($this->isM()) {
                $ret[] = $this->m === false ? 0 : $this->m ;
            }
        }
        return $ret;
    }
    
    
    /****************************** PROTECTED ******************************/
    protected function getWKTBasetype()
    {
        return static::WKT_BASETYPE;
    }
    
    protected function getGeoJSONBasetype()
    {
        return static::GEOJSON_BASETYPE;
    }
    
    
    /****************************** PRIVATE ******************************/
    /**
     * Initializes Geometry with coordinates.
     * 
     * @param   float   $x      X coordinate
     * @param   float   $y      Y coordinate
     * @param   float   $z      Z coordinate
     * @param   float   $m      M coordinate
     */
    private function init($x = null, $y = null, $z = null, $m = null)
    {
        if ($x === null xor $y === null) {
            throw new ShapefileException(Shapefile::ERR_GEOM_POINT_NOT_VALID);
        }
        if ($x !== null && $y !== null) {
            $this->x = $this->validateCoordValue($x);
            $this->y = $this->validateCoordValue($y);
            $this->setFlagEmpty(false);
            if ($z !== null) {
                $this->z = $this->validateCoordValue($z);
                $this->setFlagZ(true);
            }
            if ($m !== null) {
                $this->m = ($m === false) ? $m : $this->validateCoordValue($m);
                $this->setFlagM(true);
            }
        }
    }
    
    /**
     * Validates a coordinate value.
     * 
     * @param   float   $value  Coordinate value
     *
     * @return float
     */
    private function validateCoordValue($value)
    {
        if (!is_numeric($value)) {
            throw new ShapefileException(Shapefile::ERR_GEOM_COORD_VALUE_NOT_VALID, $value);
        }
        return floatval($value);
    }
    
}
