<?php

/**
 * PHP Shapefile - PHP library to read and write ESRI Shapefiles, compatible with WKT and GeoJSON
 *
 * @package Shapefile
 * @author  Gaspare Sganga
 * @version 3.4.0
 * @license MIT
 * @link    https://gasparesganga.com/labs/php-shapefile/
 */

namespace Shapefile\Geometry;

use Shapefile\Shapefile;
use Shapefile\ShapefileException;

/**
 * MultiPoint Geometry.
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
 *      MULTIPOINT [Z][M] (x y z m, x y z m)
 *      N.B.: Points coordinates may be enclosed in additional brackets: MULTIPOINT ((x y z m), (x y z m))
 *
 *  - GeoJSON:
 *      {
 *          "type": "MultiPoint" / "MultiPointM"
 *          "coordinates": [
 *              [x, y, z] / [x, y, m] / [x, y, z, m]
 *          ]
 *      }
 */
class MultiPoint extends GeometryCollection
{
    /**
     * WKT and GeoJSON basetypes, collection class type
     */
    const WKT_BASETYPE      = 'MULTIPOINT';
    const GEOJSON_BASETYPE  = 'MultiPoint';
    const COLLECTION_CLASS  = 'Point';
    
    
    /////////////////////////////// PUBLIC ///////////////////////////////
    public function initFromArray($array)
    {
        $this->checkInit();
        if (!isset($array['points']) || !is_array($array['points'])) {
            throw new ShapefileException(Shapefile::ERR_INPUT_ARRAY_NOT_VALID);
        }
        foreach ($array['points'] as $coordinates) {
            $Point = new Point();
            $Point->initFromArray($coordinates);
            $this->addPoint($Point);
        }
        return $this;
    }
    
    public function initFromWKT($wkt)
    {
        $this->checkInit();
        $wkt = $this->wktSanitize($wkt);
        if (!$this->wktIsEmpty($wkt)) {
            $force_z = $this->wktIsZ($wkt);
            $force_m = $this->wktIsM($wkt);
            foreach (explode(',', str_replace(array('(', ')'), '', $this->wktExtractData($wkt))) as $wkt_coordinates) {
                $coordinates = $this->wktParseCoordinates($wkt_coordinates, $force_z, $force_m);
                $Point = new Point($coordinates['x'], $coordinates['y'], $coordinates['z'], $coordinates['m']);
                $this->addPoint($Point);
            }
        }
        return $this;
    }
    
    public function initFromGeoJSON($geojson)
    {
        $this->checkInit();
        $geojson = $this->geojsonSanitize($geojson);
        if ($geojson !== null) {
            foreach ($geojson['coordinates'] as $geojson_coordinates) {
                $coordinates = $this->geojsonParseCoordinates($geojson_coordinates, $geojson['flag_m']);
                $Point = new Point($coordinates['x'], $coordinates['y'], $coordinates['z'], $coordinates['m']);
                $this->addPoint($Point);
            }
        }
        return $this;
    }
    
    
    public function getArray()
    {
        $points = [];
        foreach ($this->getPoints() as $Point) {
            $points[] = $Point->getArray();
        }
        return [
            'numpoints' => $this->getNumGeometries(),
            'points'    => $points,
        ];
    }
    
    public function getWKT()
    {
        $ret = $this->wktInitializeOutput();
        if (!$this->isEmpty()) {
            $points = [];
            foreach ($this->getPoints() as $Point) {
                $points[] = implode(' ', $Point->getRawArray());
            }
            $ret .= '(' . implode(', ', $points) . ')';
        }
        return $ret;
    }
    
    public function getGeoJSON($flag_bbox = true, $flag_feature = false)
    {
        if ($this->isEmpty()) {
            return 'null';
        }
        $coordinates = [];
        foreach ($this->getPoints() as $Point) {
            $coordinates[] = $Point->getRawArray();
        }
        return $this->geojsonPackOutput($coordinates, $flag_bbox, $flag_feature);
    }
    
    
    /**
     * Adds a point to the collection.
     *
     * @param   \Shapefile\Geometry\Point   $Point
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    public function addPoint(Point $Point)
    {
        $this->addGeometry($Point);
        return $this;
    }
    
    /**
     * Gets a point at specified index from the collection.
     *
     * @param   int     $index      The index of the point.
     *
     * @return  \Shapefile\Geometry\Point
     */
    public function getPoint($index)
    {
        return $this->getGeometry($index);
    }
    
    /**
     * Gets all the points in the collection.
     *
     * @return  \Shapefile\Geometry\Point[]
     */
    public function getPoints()
    {
        return $this->getGeometries();
    }
    
    /**
     * Gets the number of points in the collection.
     *
     * @return  int
     */
    public function getNumPoints()
    {
        return $this->getNumGeometries();
    }
    
    
    public function getSHPBasetype()
    {
        return Shapefile::SHAPE_TYPE_MULTIPOINT;
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
