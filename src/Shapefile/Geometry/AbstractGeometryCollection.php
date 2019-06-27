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
 * Abstract base class for all Geometry collections.
 * It defines some common public methods and some helper protected functions.
 */
abstract class AbstractGeometryCollection extends AbstractGeometry
{
    /**
     * @var AbstractGeometry[]  The actual geometries in the collection.
     *                          They are enforced to be all of the same type by addGeometry() method.
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
     * @param   AbstractGeometry[]  $geometries     Optional array of geometries to initialize the collection.
     */
    public function __construct(array $geometries = null)
    {
        $classname = $this->getCollectionClass();
        if ($geometries !== null) {
            foreach ($geometries as $Geometry) {
                if (is_a($Geometry, $classname)) {
                    $this->addGeometry($Geometry);
                } else {
                    throw new ShapefileException(Shapefile::ERR_INPUT_GEOMETRY_TYPE_NOT_VALID, $classname);
                }
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
            foreach ($this->geometries as $Geometry) {
                $bbox = $Geometry->getBoundingBox();
                if (!$ret) {
                    $ret = $bbox;
                } elseif ($bbox) {
                    $ret['xmin'] = $bbox['xmin'] < $ret['xmin'] ? $bbox['xmin'] : $ret['xmin'];
                    $ret['xmax'] = $bbox['xmax'] > $ret['xmax'] ? $bbox['xmax'] : $ret['xmax'];
                    $ret['ymin'] = $bbox['ymin'] < $ret['ymin'] ? $bbox['ymin'] : $ret['ymin'];
                    $ret['ymax'] = $bbox['ymax'] > $ret['ymax'] ? $bbox['ymax'] : $ret['ymax'];
                    if ($this->isZ()) {
                        $ret['zmin'] = $bbox['zmin'] < $ret['zmin'] ? $bbox['zmin'] : $ret['zmin'];
                        $ret['zmax'] = $bbox['zmax'] > $ret['zmax'] ? $bbox['zmax'] : $ret['zmax'];
                    }
                    if ($this->isM()) {
                        $ret['mmin'] = ($ret['mmin'] === false || $bbox['mmin'] < $ret['mmin']) ? $bbox['mmin'] : $ret['mmin'];
                        $ret['mmax'] = ($ret['mmax'] === false || $bbox['mmax'] > $ret['mmax']) ? $bbox['mmax'] : $ret['mmax'];
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
     * @param   AbstractGeometry    $Geometry
     */
    protected function addGeometry($Geometry)
    {
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
     * @return  AbstractGeometry
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
     * @return  AbstractGeometry[]
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
