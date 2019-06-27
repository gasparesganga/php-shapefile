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
 * Polygon Geometry.
 *
 *  - Array: [
 *      "numrings"  => n
 *      "rings"     => [
 *          [
 *              "numpoints" => n
 *              "points"    => [
 *                  [
 *                      "x" => x
 *                      "y" => y
 *                      "z" => z
 *                      "m" => m
 *                  ]
 *              ]
 *          ]
 *      ]
 *  ]
 *  
 *  - WKT:
 *      POLYGON [Z][M] ((x y z m, x y z m, x y z m, x y z m), (x y z m, x y z m, x y z m))
 *
 *  - GeoJSON:
 *      {
 *          "type": "Polygon" / "PolygonM"
 *          "coordinates": [
 *              [
 *                  [x, y, z] / [x, y, m] / [x, y, z, m]
 *              ]
 *          ]
 *      }
 */
class Polygon extends MultiLinestring
{
    /**
     * WKT and GeoJSON basetypes, collection class type
     */
    const WKT_BASETYPE      = 'POLYGON';
    const GEOJSON_BASETYPE  = 'Polygon';
    const COLLECTION_CLASS  = 'Linestring';
    
    
    /////////////////////////////// PUBLIC ///////////////////////////////
    public function initFromArray($array)
    {
        $this->checkInit();
        if (!isset($array['rings']) || !is_array($array['rings'])) {
            throw new ShapefileException(Shapefile::ERR_INPUT_ARRAY_NOT_VALID);
        }
        foreach ($array['rings'] as $part) {
            if (!isset($part['points']) || !is_array($part['points'])) {
                throw new ShapefileException(Shapefile::ERR_INPUT_ARRAY_NOT_VALID);
            }
            $Linestring = new Linestring();
            foreach ($part['points'] as $coordinates) {
                $Point = new Point();
                $Point->initFromArray($coordinates);
                $Linestring->addPoint($Point);
            }
            $this->addLinestring($Linestring);
        }
    }
    
    
    public function getArray()
    {
        $rings = [];
        foreach ($this->getLinestrings() as $Linestring) {
            $rings[] = $Linestring->getArray();
        }
        return [
            'numrings'  => $this->getNumGeometries(),
            'rings'     => $rings,
        ];
    }
    
    
    /**
     * Adds a ring to the collection.
     *
     * @param   Linestring  $Linestring
     */
    public function addRing(Linestring $Linestring)
    {
        return $this->addGeometry($Linestring);
    }
    
    /**
     * Gets a ring at specified index from the collection.
     *
     * @param   integer $index      The index of the ring.
     *
     * @return  Linestring
     */
    public function getRing($index)
    {
        return $this->getGeometry($index);
    }
    
    /**
     * Gets all the rings in the collection.
     * 
     * @return  Linestring[]
     */
    public function getRings()
    {
        return $this->getGeometries();
    }
    
    /**
     * Gets the number of rings in the collection.
     * 
     * @return  integer
     */
    public function getNumRings()
    {
        return $this->getNumGeometries();
    }
    
    /**
     * Gets the polygon outer ring.
     * 
     * @return  Linestring
     */
    public function getOuterRing()
    {
        return $this->isEmpty() ? null : $this->getGeometry(0);
    }
    
    /**
     * Gets the polygon inners rings.
     * 
     * @return  Linestring[]
     */
    public function getInnerRings()
    {
        return array_slice($this->getGeometries(), 1);
    }
    
    
    public function getSHPBasetype()
    {
        return Shapefile::SHAPE_TYPE_POLYGON;
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
