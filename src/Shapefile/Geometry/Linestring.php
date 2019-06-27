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

namespace Shapefile\Geometry;

use Shapefile\Shapefile;
use Shapefile\ShapefileException;

/**
 * Linestring Geometry.
 *
 *  - Array: [
 *      "numpoints" => n
 *      "points"    => [
 *          [
 *              "x" => x
 *              "y" => y
 *              "z" => z
 *              "m" => m
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
