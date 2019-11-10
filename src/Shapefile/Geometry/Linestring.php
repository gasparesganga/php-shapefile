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
 * Linestring Geometry.
 *
 *  - Array: [
 *      [numpoints] => int
 *      [points]    => [
 *          [
 *              [x] => float
 *              [y] => float
 *              [z] => float
 *              [m] => float/bool
 *          ]
 *      ]
 *  ]
 *  
 *  - WKT:
 *      LINESTRING [Z][M] (x y z m, x y z m)
 *
 *  - GeoJSON:
 *      {
 *          "type": "Linestring" / "LinestringM"
 *          "coordinates": [
 *              [x, y, z] / [x, y, m] / [x, y, z, m]
 *          ]
 *      }
 */
class Linestring extends MultiPoint
{
    /**
     * WKT and GeoJSON basetypes, collection class type
     */
    const WKT_BASETYPE      = 'LINESTRING';
    const GEOJSON_BASETYPE  = 'Linestring';
    const COLLECTION_CLASS  = 'Point';
    
    
    /////////////////////////////// PUBLIC ///////////////////////////////
    /**
     * Checks whether the linestring is a closed ring or not.
     * A closed ring has at least 4 vertices and the first and last ones must be the same.
     * 
     * @return  bool
     */
    public function isClosedRing()
    {
        return $this->getNumPoints() >= 4 && $this->getPoint(0) == $this->getPoint($this->getNumPoints() - 1);
    }
    
    
    public function getSHPBasetype()
    {
        return Shapefile::SHAPE_TYPE_POLYLINE;
    }
    
    
    /////////////////////////////// PROTECTED ///////////////////////////////
    protected function getWKTBasetype()
    {
        return static::WKT_BASETYPE;
    }
    
    protected function getGeoJSONBasetype()
    {
        return static::GEOJSON_BASETYPE;
    }
    
    protected function getCollectionClass()
    {
        return __NAMESPACE__ . '\\' . static::COLLECTION_CLASS;
    }
    
}
