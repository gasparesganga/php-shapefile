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
 *          "type": "LineString" / "LineStringM"
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
    const GEOJSON_BASETYPE  = 'LineString';
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
    
    /**
     * Forces the linestring to be a closed ring.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    public function forceClosedRing()
    {
        if (!$this->checkRingNumPoints()) {
            throw new ShapefileException(Shapefile::ERR_GEOM_RING_NOT_ENOUGH_VERTICES);
        }
        if (!$this->isClosedRing()) {
            $this->addPoint($this->getPoint(0));
        }
        return $this;
    }
    
    
    /**
     * Checks whether a ring is clockwise or not (it works with open rings too).
     *
     * Throws an exception if ring area is too small and cannot determine its orientation.
     * Returns Shapefile::UNDEFINED or throw an exception if there are not enough points.
     *
     * @param   bool    $flag_throw_exception   Optional flag to throw an exception if there are not enough points.
     *
     * @return  bool|Shapefile::UNDEFINED
     */
    public function isClockwise($flag_throw_exception = false)
    {
        if ($this->isEmpty()) {
            return Shapefile::UNDEFINED;
        }
        
        if (!$this->checkRingNumPoints()) {
            if ($flag_throw_exception) {
                throw new ShapefileException(Shapefile::ERR_GEOM_RING_NOT_ENOUGH_VERTICES);
            }
            return Shapefile::UNDEFINED;
        }
        
        $area = $this->computeGaussArea($this->getArray()['points']);
        if (!$area) {
            throw new ShapefileException(Shapefile::ERR_GEOM_RING_AREA_TOO_SMALL);
        }
        
        // Negative area means clockwise direction
        return $area < 0;
    }
    
    /**
     * Forces the ring to be in clockwise direction (it works with open rings too).
     * Throws an exception if direction is undefined.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    public function forceClockwise()
    {
        if ($this->isClockwise(true) === false) {
            $this->reverseGeometries();
        }
        return $this;
    }
    
    /**
     * Forces the ring to be in counterclockwise direction (it works with open rings too).
     * Throws an exception if direction is undefined.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    public function forceCounterClockwise()
    {
        if ($this->isClockwise(true) === true) {
            $this->reverseGeometries();
        }
        return $this;
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
    
    
    /////////////////////////////// PRIVATE ///////////////////////////////
    /**
     * Checks if the linestring has enough points to be a ring.
     */
    private function checkRingNumPoints()
    {
        return $this->getNumPoints() >= 3;
    }
    
    
    /**
     * Computes ring area using a Gauss-like formula.
     * The target is to determine whether it is positive or negative, not the exact area.
     *
     * An optional $exp parameter is used to deal with very small areas.
     *
     * @param   array   $points     Array of points. Each element must have "x" and "y" members.
     * @param   int     $exp        Optional exponent to deal with small areas (coefficient = 10^exponent).
     *
     * @return  float
     */
    private function computeGaussArea($points, $exp = 0)
    {
        // If a coefficient of 10^9 is not enough, give up!
        if ($exp > 9) {
            return 0;
        }
        $coef = pow(10, $exp);
        
        // At least 3 points (in case of an open ring) are needed to compute the area
        $num_points = count($points);
        if ($num_points < 3) {
            return 0;
        }
        
        // Use Gauss's area formula (no need to be strict here, hence no 1/2 coefficient and no check for closed rings)
        $num_points--;
        $tot = 0;
        for ($i = 0; $i < $num_points; ++$i) {
            $tot += ($coef * $points[$i]['x'] * $points[$i + 1]['y']) - ($coef * $points[$i]['y'] * $points[$i + 1]['x']);
        }
        $tot += ($coef * $points[$num_points]['x'] * $points[0]['y']) - ($coef * $points[$num_points]['y'] * $points[0]['x']);
        
        // If area is too small, increase coefficient exponent and retry
        if ($tot == 0) {
            return $this->computeGaussArea($points, $exp + 3);
        }
        
        return $tot;
    }
}
