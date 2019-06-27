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
 * MultiPolygon Geometry.
 *
 *  - Array: [
 *      "numparts"  => n
 *      "parts"     => [
 *          [
 *              "numrings"  => n
 *              "rings"     => [
 *                  [
 *                      "numpoints" => n
 *                      "points"    => [
 *                          [
 *                              "x" => x
 *                              "y" => y
 *                              "z" => z
 *                              "m" => m
 *                          ]
 *                      ]
 *                  ]
 *              ]
 *          ]
 *      ]
 *  ]
 *  
 *  - WKT:
 *      MULTIPOLYGON [Z][M] (((x y z m, x y z m, x y z m, x y z m), (x y z m, x y z m, x y z m)), ((x y z m, x y z m, x y z m, x y z m), (x y z m, x y z m, x y z m)))
 *
 *  - GeoJSON:
 *      {
 *          "type": "MultiPolygon" / "MultiPolygonM"
 *          "coordinates": [
 *              [
 *                  [
 *                      [x, y, z] / [x, y, m] / [x, y, z, m]
 *                  ]
 *              ]
 *          ]
 *      }
 */
class MultiPolygon extends AbstractGeometryCollection
{
    /**
     * WKT and GeoJSON basetypes, collection class type
     */
    const WKT_BASETYPE      = 'MULTIPOLYGON';
    const GEOJSON_BASETYPE  = 'MultiPolygon';
    const COLLECTION_CLASS  = 'Polygon';
    
    
    /////////////////////////////// PUBLIC ///////////////////////////////
    public function initFromArray($array)
    {
        $this->checkInit();
        if (!isset($array['parts']) || !is_array($array['parts'])) {
            throw new ShapefileException(Shapefile::ERR_INPUT_ARRAY_NOT_VALID);
        }
        foreach ($array['parts'] as $part) {
            if (!isset($part['rings']) || !is_array($part['rings'])) {
                throw new ShapefileException(Shapefile::ERR_INPUT_ARRAY_NOT_VALID);
            }
            $Polygon = new Polygon();
            foreach ($part['rings'] as $part) {
                if (!isset($part['points']) || !is_array($part['points'])) {
                    throw new ShapefileException(Shapefile::ERR_INPUT_ARRAY_NOT_VALID);
                }
                $Linestring = new Linestring();
                foreach ($part['points'] as $coordinates) {
                    $Point = new Point();
                    $Point->initFromArray($coordinates);
                    $Linestring->addPoint($Point);
                }
                $Polygon->addLinestring($Linestring);
            }
            $this->addPolygon($Polygon);
        }
    }
    
    public function initFromWKT($wkt)
    {
        $this->checkInit();
        $wkt = $this->wktSanitize($wkt);
        if (!$this->wktIsEmpty($wkt)) {
            $force_z = $this->wktIsZ($wkt);
            $force_m = $this->wktIsM($wkt);
            foreach (explode(')),((', substr($this->wktExtractData($wkt), 2, -2)) as $part) {
                $Polygon = new Polygon();
                foreach (explode('),(', $part) as $ring) {
                    $Linestring = new Linestring();
                    foreach (explode(',', $ring) as $wkt_coordinates) {
                        $coordinates = $this->wktParseCoordinates($wkt_coordinates, $force_z, $force_m);
                        $Point = new Point($coordinates['x'], $coordinates['y'], $coordinates['z'], $coordinates['m']);
                        $Linestring->addPoint($Point);
                    }
                    $Polygon->addLinestring($Linestring);
                }
                $this->addPolygon($Polygon);
            }
        }
    }
    
    public function initFromGeoJSON($geojson)
    {
        $this->checkInit();
        $geojson = $this->geojsonSanitize($geojson);
        if ($geojson !== null) {
            $force_m = $this->geojsonIsM($geojson['type']);
            foreach ($geojson['coordinates'] as $part) {
                $Polygon = new Polygon();
                foreach ($part as $ring) {
                    $Linestring = new Linestring();
                    foreach ($ring as $geojson_coordinates) {
                        $coordinates = $this->geojsonParseCoordinates($geojson_coordinates, $force_m);
                        $Point = new Point($coordinates['x'], $coordinates['y'], $coordinates['z'], $coordinates['m']);
                        $Linestring->addPoint($Point);
                    }
                    $Polygon->addLinestring($Linestring);
                }
                $this->addPolygon($Polygon);
            }
        }
    }
    
    
    public function getArray()
    {
        $parts = [];
        foreach ($this->getPolygons() as $Polygon) {
            $parts[] = $Polygon->getArray();
        }
        return [
            'numparts'  => $this->getNumGeometries(),
            'rings'     => $parts,
        ];
    }
    
    public function getWKT()
    {
        $ret = $this->wktInitializeOutput();
        if (!$this->isEmpty()) {
            $parts = [];
            foreach ($this->getPolygons() as $Polygon) {
                $rings = [];
                foreach ($Polygon->getLinestrings() as $Linestring) {
                    $points = [];
                    foreach ($Linestring->getPoints() as $Point) {
                        $points[] = implode(' ', $Point->getRawArray());
                    }
                    $rings[] = '(' . implode(', ', $points) . ')';
                }
                $parts[] = '(' . implode(', ', $rings) . ')';
                
            }
            $ret .= '(' . implode(', ', $parts) . ')';
        }
        return $ret;
    }
    
    public function getGeoJSON($flagBBox = true, $flagFeature = false)
    {
        if ($this->isEmpty()) {
            return 'null';
        }
        $coordinates = [];
        foreach ($this->getPolygons() as $Polygon) {
            $parts = [];
            foreach ($Polygon->getLinestrings() as $Linestring) {
                $rings = [];
                foreach ($Linestring->getPoints() as $Point) {
                    $rings[] = $Point->getRawArray();
                }
                $parts[] = $rings;
            }
            $coordinates[] = $parts;
        }
        return $this->geojsonPackOutput($coordinates, $flagBBox, $flagFeature);
    }
    
    
    /**
     * Adds a polygon to the collection.
     *
     * @param   Polygon     $Polygon
     */
    public function addPolygon(Polygon $Polygon)
    {
        return $this->addGeometry($Polygon);
    }
    
    /**
     * Gets a polygon at specified index from the collection.
     *
     * @param   integer $index      The index of the polygon.
     *
     * @return  Polygon
     */
    public function getPolygon($index)
    {
        return $this->getGeometry($index);
    }
    
    /**
     * Gets all the polygons in the collection.
     * 
     * @return  Polygon[]
     */
    public function getPolygons()
    {
        return $this->getGeometries();
    }
    
    /**
     * Gets the number of polygons in the collection.
     * 
     * @return  integer
     */
    public function getNumPolygons()
    {
        return $this->getNumGeometries();
    }
    
    
    public function getSHPBasetype()
    {
        return Shapefile::SHAPE_TYPE_POLYGON;
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
    
    protected function getCollectionClass()
    {
        return __NAMESPACE__ . '\\' . static::COLLECTION_CLASS;
    }
    
}
