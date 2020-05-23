<?php

/**
 * PHP Shapefile - PHP library to read and write ESRI Shapefiles, compatible with WKT and GeoJSON
 *
 * @package Shapefile
 * @author  Gaspare Sganga
 * @version 3.3.0
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
 *      [numrings]  => int
 *      [rings]     => [
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
    
    
    /**
     * @var int     Action to perform on polygon rings.
     */
    private $closed_rings;
    
    /**
     * @var int     Orientation to force for polygon rings.
     */
    private $force_orientation;
    
    
    
    /////////////////////////////// PUBLIC ///////////////////////////////
    /**
     * Constructor.
     *
     * @param   \Shapefile\Geometry\Linestring[]    $linestrings        Optional array of linestrings to initialize the polygon.
     * @param   int                                 $closed_rings       Optional action to perform on polygon rings. Possible values:
     *                                                                      - Shapefile::ACTION_IGNORE
     *                                                                      - Shapefile::ACTION_CHECK
     *                                                                      - Shapefile::ACTION_FORCE
     * @param   int                                 $force_orientation  Optional orientation to force for polygon rings. Possible values:
     *                                                                      - Shapefile::ORIENTATION_CLOCKWISE
     *                                                                      - Shapefile::ORIENTATION_COUNTERCLOCKWISE
     *                                                                      - Shapefile::ORIENTATION_UNCHANGED
     */
    public function __construct(array $linestrings = null, $closed_rings = Shapefile::ACTION_CHECK, $force_orientation = Shapefile::ORIENTATION_COUNTERCLOCKWISE)
    {
        $this->closed_rings         = $closed_rings;
        $this->force_orientation    = $force_orientation;
        parent::__construct($linestrings);
    }
    
    
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
            $this->addRing($Linestring);
        }
        return $this;
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
     * @param   \Shapefile\Geometry\Linestring  $Linestring
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    public function addRing(Linestring $Linestring)
    {
        $this->addGeometry($Linestring);
        return $this;
    }
    
    /**
     * Gets a ring at specified index from the collection.
     *
     * @param   int     $index      The index of the ring.
     *
     * @return  \Shapefile\Geometry\Linestring
     */
    public function getRing($index)
    {
        return $this->getGeometry($index);
    }
    
    /**
     * Gets all the rings in the collection.
     *
     * @return  \Shapefile\Geometry\Linestring[]
     */
    public function getRings()
    {
        return $this->getGeometries();
    }
    
    /**
     * Gets the number of rings in the collection.
     *
     * @return  int
     */
    public function getNumRings()
    {
        return $this->getNumGeometries();
    }
    
    /**
     * Gets the polygon outer ring.
     *
     * @return  \Shapefile\Geometry\Linestring
     */
    public function getOuterRing()
    {
        return $this->isEmpty() ? null : $this->getRing(0);
    }
    
    /**
     * Gets polygon inners rings.
     *
     * @return  \Shapefile\Geometry\Linestring[]
     */
    public function getInnerRings()
    {
        return array_slice($this->getRings(), 1);
    }
    
    
    /**
     * Forces polygon rings to be closed.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    public function forceClosedRings()
    {
        foreach ($this->getRings() as $Linestring) {
            $Linestring->forceClosedRing();
        }
        return $this;
    }
    
    
    /**
     * Checks whether polygon outer ring has a clockwise orientation and all the inner rings have a counterclockwise one.
     * Note that a false return value does not guarantee polygon is strictly counterclockwise. Use Polygon::forceCounterClockwise() to enforce that!
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
        if ($this->getOuterRing()->isClockwise(true) === false) {
            return false;
        }
        foreach ($this->getInnerRings() as $Linestring) {
            if ($Linestring->isClockwise(true) === true) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Checks whether polygon outer ring has a counterclockwise orientation and all the inner rings have a clockwise one.
     * Note that a false return value does not guarantee polygon is strictly clockwise. Use Polygon::forceClockwise() to enforce that!
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
        if ($this->getOuterRing()->isClockwise(true) === true) {
            return false;
        }
        foreach ($this->getInnerRings() as $Linestring) {
            if ($Linestring->isClockwise(true) === false) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Forces polygon outer ring to have a clockwise orientation and all the inner rings to have a counterclockwise one.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    public function forceClockwise()
    {
        if (!$this->isEmpty()) {
            $this->getOuterRing()->forceClockwise();
            foreach ($this->getInnerRings() as $Linestring) {
                $Linestring->forceCounterClockwise();
            }
        }
        return $this;
    }
    
    /**
     * Forces polygon outer ring to have a counterclockwise orientation and all the inner rings to have a clockwise one.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    public function forceCounterClockwise()
    {
        if (!$this->isEmpty()) {
            $this->getOuterRing()->forceCounterClockwise();
            foreach ($this->getInnerRings() as $Linestring) {
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
     * Performs selected action and eventually forces orientation for polygon rings.
     *
     * @param   \Shapefile\Geometry\Geometry    $Linestring
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    protected function addGeometry(Geometry $Linestring)
    {
        parent::addGeometry($Linestring);
        
        // Closed rings
        if ($this->closed_rings == Shapefile::ACTION_FORCE) {
            $Linestring->forceClosedRing();
        } elseif ($this->closed_rings == Shapefile::ACTION_CHECK && !$Linestring->isClosedRing()) {
            throw new ShapefileException(Shapefile::ERR_GEOM_POLYGON_OPEN_RING);
        }
        
        // Orientation
        if ($this->force_orientation == Shapefile::ORIENTATION_CLOCKWISE) {
            $Linestring->{($this->getNumGeometries() == 1) ? 'forceClockwise' : 'forceCounterClockwise'}();
        } elseif ($this->force_orientation == Shapefile::ORIENTATION_COUNTERCLOCKWISE) {
            $Linestring->{($this->getNumGeometries() == 1) ? 'forceCounterClockwise' : 'forceClockwise'}();
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
