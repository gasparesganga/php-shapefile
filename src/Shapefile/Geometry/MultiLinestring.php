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
 * MultiLinestring Geometry.
 *
 *  - Array: [
 *      [numparts]  => int
 *      [parts]     => [
 *          [
 *              [numpoints] => int
 *              [points]    => [
 *                  [
 *                      [x] => float
 *                      [y] => float
 *                      [z] => float
 *                      [m] => float/bool
 *                  ]
 *              ]
 *          ]
 *      ]
 *  ]
 *
 *  - WKT:
 *      MULTILINESTRING [Z][M] ((x y z m, x y z m, x y z m), (x y z m, x y z m))
 *
 *  - GeoJSON:
 *      {
 *          "type": "MultiLineString" / "MultiLineStringM"
 *          "coordinates": [
 *              [
 *                  [x, y, z] / [x, y, m] / [x, y, z, m]
 *              ]
 *          ]
 *      }
 */
class MultiLinestring extends GeometryCollection
{
    /**
     * WKT and GeoJSON basetypes, collection class type
     */
    const WKT_BASETYPE      = 'MULTILINESTRING';
    const GEOJSON_BASETYPE  = 'MultiLineString';
    const COLLECTION_CLASS  = 'Linestring';
    
    
    /////////////////////////////// PUBLIC ///////////////////////////////
    public function initFromArray($array)
    {
        $this->checkInit();
        if (!isset($array['parts']) || !is_array($array['parts'])) {
            throw new ShapefileException(Shapefile::ERR_INPUT_ARRAY_NOT_VALID);
        }
        foreach ($array['parts'] as $part) {
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
        return $this;
    }
    
    public function initFromWKT($wkt)
    {
        $this->checkInit();
        $wkt = $this->wktSanitize($wkt);
        if (!$this->wktIsEmpty($wkt)) {
            $force_z = $this->wktIsZ($wkt);
            $force_m = $this->wktIsM($wkt);
            foreach (explode('),(', substr($this->wktExtractData($wkt), 1, -1)) as $part) {
                $Linestring = new Linestring();
                foreach (explode(',', $part) as $wkt_coordinates) {
                    $coordinates = $this->wktParseCoordinates($wkt_coordinates, $force_z, $force_m);
                    $Point = new Point($coordinates['x'], $coordinates['y'], $coordinates['z'], $coordinates['m']);
                    $Linestring->addPoint($Point);
                }
                $this->addLinestring($Linestring);
            }
        }
        return $this;
    }
    
    public function initFromGeoJSON($geojson)
    {
        $this->checkInit();
        $geojson = $this->geojsonSanitize($geojson);
        if ($geojson !== null) {
            foreach ($geojson['coordinates'] as $part) {
                if (!is_array($part)) {
                    throw new ShapefileException(Shapefile::ERR_INPUT_GEOJSON_NOT_VALID, 'Wrong coordinates format');
                }
                $Linestring = new Linestring();
                foreach ($part as $geojson_coordinates) {
                    $coordinates = $this->geojsonParseCoordinates($geojson_coordinates, $geojson['flag_m']);
                    $Point = new Point($coordinates['x'], $coordinates['y'], $coordinates['z'], $coordinates['m']);
                    $Linestring->addPoint($Point);
                }
                $this->addLinestring($Linestring);
            }
        }
        return $this;
    }
    
    
    public function getArray()
    {
        $parts = [];
        foreach ($this->getLinestrings() as $Linestring) {
            $parts[] = $Linestring->getArray();
        }
        return [
            'numparts'  => $this->getNumGeometries(),
            'parts'     => $parts,
        ];
    }
    
    public function getWKT()
    {
        $ret = $this->wktInitializeOutput();
        if (!$this->isEmpty()) {
            $parts = [];
            foreach ($this->getLinestrings() as $Linestring) {
                $points = [];
                foreach ($Linestring->getPoints() as $Point) {
                    $points[] = implode(' ', $Point->getRawArray());
                }
                $parts[] = '(' . implode(', ', $points) . ')';
            }
            $ret .= '(' . implode(', ', $parts) . ')';
        }
        return $ret;
    }
    
    public function getGeoJSON($flag_bbox = true, $flag_feature = false)
    {
        if ($this->isEmpty()) {
            return 'null';
        }
        $coordinates = [];
        foreach ($this->getLinestrings() as $Linestring) {
            $parts = [];
            foreach ($Linestring->getPoints() as $Point) {
                $parts[] = $Point->getRawArray();
            }
            $coordinates[] = $parts;
        }
        return $this->geojsonPackOutput($coordinates, $flag_bbox, $flag_feature);
    }
    
    
    /**
     * Adds a linestring to the collection.
     *
     * @param   \Shapefile\Geometry\Linestring  $Linestring
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    public function addLinestring(Linestring $Linestring)
    {
        $this->addGeometry($Linestring);
        return $this;
    }
    
    /**
     * Gets a linestring at specified index from the collection.
     *
     * @param   int     $index  The index of the linestring.
     *
     * @return  \Shapefile\Geometry\Linestring
     */
    public function getLinestring($index)
    {
        return $this->getGeometry($index);
    }
    
    /**
     * Gets all the linestrings in the collection.
     *
     * @return  \Shapefile\Geometry\Linestring[]
     */
    public function getLinestrings()
    {
        return $this->getGeometries();
    }
    
    /**
     * Gets the number of linestrings in the collection.
     *
     * @return  int
     */
    public function getNumLinestrings()
    {
        return $this->getNumGeometries();
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
