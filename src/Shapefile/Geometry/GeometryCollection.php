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
 * Abstract base class for all Geometry Collections.
 * It defines some common public methods and some helper protected functions.
 */
abstract class GeometryCollection extends Geometry
{
    /**
     * @var Geometry[]      The actual geometries in the collection.
     *                      They are enforced to be all of the same type by addGeometry() method.
     */
    protected $geometries = [];
    
    
    
    /////////////////////////////// ABSTRACT ///////////////////////////////
    /**
     * Gets the class name of the base geometries in the collection.
     * 
     * @return  string
     */
    abstract protected function getCollectionClass();
    
    
    
    /////////////////////////////// PUBLIC ///////////////////////////////
    /**
     * Constructor.
     * 
     * @param   Geometry[]      $geometries     Optional array of geometries to initialize the collection.
     */
    public function __construct(array $geometries = null)
    {
        if ($geometries !== null) {
            foreach ($geometries as $Geometry) {
               $this->addGeometry($Geometry);
            }
        }
    }
    
    
    public function getBoundingBox()
    {
        if ($this->isEmpty()) {
            return null;
        }
        $ret = $this->getCustomBoundingBox();
        if (!$ret) {
            $is_z = $this->isZ();
            $is_m = $this->isM();
            foreach ($this->geometries as $Geometry) {
                $bbox = $Geometry->getBoundingBox();
                if (!$ret) {
                    $ret = $bbox;
                } elseif ($bbox) {
                    if ($bbox['xmin'] < $ret['xmin']) {
                        $ret['xmin'] = $bbox['xmin'];
                    }
                    if ($bbox['xmax'] > $ret['xmax']) {
                        $ret['xmax'] = $bbox['xmax'];
                    }
                    if ($bbox['ymin'] < $ret['ymin']) {
                        $ret['ymin'] = $bbox['ymin'];
                    }
                    if ($bbox['ymax'] > $ret['ymax']) {
                        $ret['ymax'] = $bbox['ymax'];
                    }
                    if ($is_z) {
                        if ($bbox['zmin'] < $ret['zmin']) {
                            $ret['zmin'] = $bbox['zmin'];
                        }
                        if ($bbox['zmax'] > $ret['zmax']) {
                            $ret['zmax'] = $bbox['zmax'];
                        }
                    }
                    if ($is_m) {
                        if ($ret['mmin'] === false || $bbox['mmin'] < $ret['mmin']) {
                            $ret['mmin'] = $bbox['mmin'];
                        }
                        if ($ret['mmax'] === false || $bbox['mmax'] > $ret['mmax']) {
                            $ret['mmax'] = $bbox['mmax'];
                        }
                    }
                }
            }
        }
        return $ret;
    }
    
    
    
    /////////////////////////////// PROTECTED ///////////////////////////////    
    /**
     * Adds a Geometry to the collection.
     * It enforces all geometries to be of the same type.
     *
     * @param   Geometry    $Geometry
     */
    protected function addGeometry(Geometry $Geometry)
    {
        if (!is_a($Geometry, $this->getCollectionClass())) {
            throw new ShapefileException(Shapefile::ERR_INPUT_GEOMETRY_TYPE_NOT_VALID, $this->getCollectionClass());
        }      
        if (!$Geometry->isEmpty()) {
            if ($this->isEmpty()) {
                $this->setFlagEmpty(false);
                $this->setFlagZ($Geometry->isZ());
                $this->setFlagM($Geometry->isM());
            } else {
                if ($this->isZ() !== $Geometry->isZ() || $this->isM() !== $Geometry->isM()) {
                    throw new ShapefileException(Shapefile::ERR_GEOM_MISMATCHED_DIMENSIONS);
                }
            }
            $this->geometries[] = $Geometry;
        }
    }
    
    /**
     * Gets a Geometry at specified index from the collection.
     * 
     * @param   integer $index      The index of the Geometry.
     *
     * @return  Geometry
     */
    protected function getGeometry($index)
    {
        if (!isset($this->geometries[$index])) {
            throw new ShapefileException(Shapefile::ERR_INPUT_GEOMETRY_INDEX_NOT_VALID, $index);
        }
        return $this->geometries[$index];
    }
    
    /**
     * Gets all the geometries in the collection.
     * 
     * @return  Geometry[]
     */
    protected function getGeometries()
    {
        return $this->geometries;
    }
    
    /**
     * Gets the number of geometries in the collection.
     * 
     * @return  integer
     */
    protected function getNumGeometries()
    {
        return count($this->geometries);
    }
      
}
