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
 * MultiPolygon Geometry.
 *
 *  - Array: [
 *      [numparts]  => int
 *      [parts]     => [
 *          [
 *              [numrings]  => int
 *              [rings]     => [
 *                  [
 *                      [numpoints] => int
 *                      [points]    => [
 *                          [
 *                              [x] => float
 *                              [y] => float
 *                              [z] => float
 *                              [m] => float/bool
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
class MultiPolygon extends GeometryCollection
{
    /**
     * WKT and GeoJSON basetypes, collection class type
     */
    const WKT_BASETYPE      = 'MULTIPOLYGON';
    const GEOJSON_BASETYPE  = 'MultiPolygon';
    const COLLECTION_CLASS  = 'Polygon';
    
    
    /**
     * @var bool    Flag representing whether a closed rings check must be performed.
     */
    private $flag_enforce_closed_rings = false;
    
    
    /////////////////////////////// PUBLIC ///////////////////////////////
    /**
     * Constructor.
     * 
     * @param   Polygon[]   $polygons                   Optional array of polygons to initialize the multipolygon.
     * @param   bool        $flag_enforce_closed_rings  Optional flag to enforce closed rings check.
     */
    public function __construct(array $polygons = null, $flag_enforce_closed_rings = true)
    {
        $this->flag_enforce_closed_rings = $flag_enforce_closed_rings;
        parent::__construct($polygons);
    }
    
    
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
            $Polygon = new Polygon(null, $this->flag_enforce_closed_rings);
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
                $Polygon = new Polygon(null, $this->flag_enforce_closed_rings);
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
                $Polygon = new Polygon(null, $this->flag_enforce_closed_rings);
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
            'parts'     => $parts,
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
    
    public function getGeoJSON($flag_bbox = true, $flag_feature = false)
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
        return $this->geojsonPackOutput($coordinates, $flag_bbox, $flag_feature);
    }
    
    
    /**
     * Adds a polygon to the collection.
     *
     * @param   Polygon     $Polygon
     */
    public function addPolygon(Polygon $Polygon)
    {
        $this->addGeometry($Polygon);
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
    
    
    /////////////////////////////// PROTECTED ///////////////////////////////
    /**
     * Enforces all linestrings forming polygons in the collection to be closed rings.
     * 
     * @param   Geometry    $Polygon
     */
    protected function addGeometry(Geometry $Polygon)
    {
        parent::addGeometry($Polygon);
        if ($this->flag_enforce_closed_rings) {
            foreach ($Polygon->getRings() as $Linestring) {
                if (!$Linestring->isClosedRing()) {
                    throw new ShapefileException(Shapefile::ERR_GEOM_POLYGON_OPEN_RING);
                }
            }
        }
    }
    
    
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
