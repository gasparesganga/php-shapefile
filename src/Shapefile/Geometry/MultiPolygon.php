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
     * @var int     Action to perform on polygons rings.
     */
    private $closed_rings;
    
    /**
     * @var int     Orientation to force for polygons rings.
     */
    private $force_orientation;
    
    
    
    /////////////////////////////// PUBLIC ///////////////////////////////
    /**
     * Constructor.
     *
     * @param   \Shapefile\Geometry\Polygon[]   $polygons           Optional array of polygons to initialize the multipolygon.
     * @param   int                             $closed_rings       Optional action to perform on polygons rings. Possible values:
     *                                                                  - Shapefile::ACTION_IGNORE
     *                                                                  - Shapefile::ACTION_CHECK
     *                                                                  - Shapefile::ACTION_FORCE
     * @param   int                             $force_orientation  Optional orientation to force for polygons rings. Possible values:
     *                                                                  - Shapefile::ORIENTATION_CLOCKWISE
     *                                                                  - Shapefile::ORIENTATION_COUNTERCLOCKWISE
     *                                                                  - Shapefile::ORIENTATION_UNCHANGED
     */
    public function __construct(array $polygons = null, $closed_rings = Shapefile::ACTION_CHECK, $force_orientation = Shapefile::ORIENTATION_COUNTERCLOCKWISE)
    {
        $this->closed_rings         = $closed_rings;
        $this->force_orientation    = $force_orientation;
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
            $Polygon = new Polygon(null, $this->closed_rings, $this->force_orientation);
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
            $this->addGeometry($Polygon, false);
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
            foreach (explode(')),((', substr($this->wktExtractData($wkt), 2, -2)) as $part) {
                $Polygon = new Polygon(null, $this->closed_rings, $this->force_orientation);
                foreach (explode('),(', $part) as $ring) {
                    $Linestring = new Linestring();
                    foreach (explode(',', $ring) as $wkt_coordinates) {
                        $coordinates = $this->wktParseCoordinates($wkt_coordinates, $force_z, $force_m);
                        $Point = new Point($coordinates['x'], $coordinates['y'], $coordinates['z'], $coordinates['m']);
                        $Linestring->addPoint($Point);
                    }
                    $Polygon->addLinestring($Linestring);
                }
                $this->addGeometry($Polygon, false);
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
                $Polygon = new Polygon(null, $this->closed_rings, $this->force_orientation);
                foreach ($part as $ring) {
                    if (!is_array($ring)) {
                        throw new ShapefileException(Shapefile::ERR_INPUT_GEOJSON_NOT_VALID, 'Wrong coordinates format');
                    }
                    $Linestring = new Linestring();
                    foreach ($ring as $geojson_coordinates) {
                        $coordinates = $this->geojsonParseCoordinates($geojson_coordinates, $geojson['flag_m']);
                        $Point = new Point($coordinates['x'], $coordinates['y'], $coordinates['z'], $coordinates['m']);
                        $Linestring->addPoint($Point);
                    }
                    $Polygon->addLinestring($Linestring);
                }
                $this->addGeometry($Polygon, false);
            }
        }
        return $this;
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
     * @param   \Shapefile\Geometry\Polygon     $Polygon
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    public function addPolygon(Polygon $Polygon)
    {
        $this->addGeometry($Polygon, true);
        return $this;
    }
    
    /**
     * Gets a polygon at specified index from the collection.
     *
     * @param   int     $index      The index of the polygon.
     *
     * @return  \Shapefile\Geometry\Polygon
     */
    public function getPolygon($index)
    {
        return $this->getGeometry($index);
    }
    
    /**
     * Gets all the polygons in the collection.
     *
     * @return  \Shapefile\Geometry\Polygon[]
     */
    public function getPolygons()
    {
        return $this->getGeometries();
    }
    
    /**
     * Gets the number of polygons in the collection.
     *
     * @return  int
     */
    public function getNumPolygons()
    {
        return $this->getNumGeometries();
    }
    
    
    /**
     * Forces multipolygon rings to be closed.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    public function forceClosedRings()
    {
        foreach ($this->getPolygons() as $Polygon) {
            $Polygon->forceClosedRings();
        }
        return $this;
    }
    
    
    /**
     * Checks whether all multipolygon outer rings have a clockwise orientation and all the inner rings have a counterclockwise one.
     * Note that a false return value does not guarantee multipolygon is strictly counterclockwise. Use MultiPolygon::forceCounterClockwise() to enforce that!
     *
     * Returns Shapefile::UNDEFINED if geometry is empty.
     *
     * @return  bool|Shapefile::UNDEFINED
     */
    public function isClockwise()
    {
        if ($this->isEmpty()) {
            return Shapefile::UNDEFINED;
        }
        
        foreach ($this->getPolygons() as $Polygon) {
            if ($Polygon->getOuterRing()->isClockwise(true) === false) {
                return false;
            }
            foreach ($Polygon->getInnerRings() as $Linestring) {
                if ($Linestring->isClockwise(true) === true) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Checks whether all multipolygon outer rings have a counterclockwise orientation and all the inner rings have a clockwise one.
     * Note that a false return value does not guarantee multipolygon is strictly clockwise. Use MultiPolygon::forceClockwise() to enforce that!
     *
     * Returns Shapefile::UNDEFINED if geometry is empty.
     *
     * @return  bool|Shapefile::UNDEFINED
     */
    public function isCounterClockwise()
    {
        if ($this->isEmpty()) {
            return Shapefile::UNDEFINED;
        }
        
        foreach ($this->getPolygons() as $Polygon) {
            if ($Polygon->getOuterRing()->isClockwise(true) === true) {
                return false;
            }
            foreach ($Polygon->getInnerRings() as $Linestring) {
                if ($Linestring->isClockwise(true) === false) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Forces all multipolygon outer rings to have a clockwise orientation and all the inner rings to have a counterclockwise one.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    public function forceClockwise()
    {
        foreach ($this->getPolygons() as $Polygon) {
            $Polygon->getOuterRing()->forceClockwise();
            foreach ($Polygon->getInnerRings() as $Linestring) {
                $Linestring->forceCounterClockwise();
            }
        }
        return $this;
    }
    
    /**
     * Forces all multipolygon outer rings to have a counterclockwise orientation and all the inner rings to have a clockwise one.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    public function forceCounterClockwise()
    {
        foreach ($this->getPolygons() as $Polygon) {
            $Polygon->getOuterRing()->forceCounterClockwise();
            foreach ($Polygon->getInnerRings() as $Linestring) {
                $Linestring->forceClockwise();
            }
        }
        return $this;
    }
    
    
    public function getSHPBasetype()
    {
        return Shapefile::SHAPE_TYPE_POLYGON;
    }
    
    
    /////////////////////////////// PROTECTED ///////////////////////////////
    /**
     * Enforces class-wide action and orientation for polygons rings.
     *
     * @param   \Shapefile\Geometry\Geometry    $Polygon
     * @param   bool                            $flag_rings_and_orientation     Optionally enforce class action and orientation for rings.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    protected function addGeometry(Geometry $Polygon, $flag_rings_and_orientation = true)
    {
        parent::addGeometry($Polygon);
        
        if ($flag_rings_and_orientation && ($this->closed_rings != Shapefile::ACTION_IGNORE || $this->force_orientation != Shapefile::ORIENTATION_UNCHANGED)) {
            foreach ($Polygon->getRings() as $i => $Linestring) {
                // Closed rings
                if ($this->closed_rings == Shapefile::ACTION_FORCE) {
                    $Linestring->forceClosedRing();
                } elseif ($this->closed_rings == Shapefile::ACTION_CHECK && !$Linestring->isClosedRing()) {
                    throw new ShapefileException(Shapefile::ERR_GEOM_POLYGON_OPEN_RING);
                }
                // Orientation
                if ($this->force_orientation == Shapefile::ORIENTATION_CLOCKWISE) {
                    $Linestring->{($i == 0) ? 'forceClockwise' : 'forceCounterClockwise'}();
                } elseif ($this->force_orientation == Shapefile::ORIENTATION_COUNTERCLOCKWISE) {
                    $Linestring->{($i == 0) ? 'forceCounterClockwise' : 'forceClockwise'}();
                }
            }
        }
        
        return $this;
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
