<?php
/**
 * PHP Shapefile - PHP library to read and write ESRI Shapefiles, compatible with WKT and GeoJSON
 * 
 * @package Shapefile
 * @author  Gaspare Sganga
 * @version 3.0.2
 * @license MIT
 * @link    https://gasparesganga.com/labs/php-shapefile/
 */

namespace Shapefile;

class ShapefileWriter extends Shapefile
{
    /**
     * @var array   Array of canonicalized absolute pathnames of open files.
     */
    private $filenames = [];
    
    /**
     * @var integer Number of records.
     */
    private $tot_records = 0;
     
    /**
     * @var integer Next available block in DBT file.
     */
    private $dbt_next_available_block = 0;
    
    /**
     * @var bool    Flag representing whether file headers have been initialized or not.
     */
    private $flag_init_headers = false;
    
    
    
    /////////////////////////////// PUBLIC ///////////////////////////////
    /**
     * Constructor.
     * 
     * @param   string|array    $files      Path to SHP file / Array of paths / Array of handles of individual files.
     * @param   array           $options    Optional associative array of options.
     */
    public function __construct($files, $options = [])
    {
        // Options
        $this->initOptions([
            Shapefile::OPTION_CPG_ENABLE_FOR_DEFAULT_CHARSET,
            Shapefile::OPTION_DBF_FORCE_ALL_CAPS,
            Shapefile::OPTION_DBF_NULL_PADDING_CHAR,
            Shapefile::OPTION_DBF_NULLIFY_INVALID_DATES,
            Shapefile::OPTION_DELETE_EMPTY_FILES,
            Shapefile::OPTION_ENFORCE_GEOMETRY_DATA_STRUCTURE,
            Shapefile::OPTION_OVERWRITE_EXISTING_FILES,
            Shapefile::OPTION_SUPPRESS_M,
            Shapefile::OPTION_SUPPRESS_Z,
        ], $options);
        
        // Open files
        $this->filenames = $this->openFiles($files, true);
    }
    
    /**
     * Destructor.
     *
     * Finalizes open files.
     * If files were NOT passed as stream resources, empty useless files will be removed.
     */
    public function __destruct()
    {
        // Write SHP, SHX, DBF and DBT headers
        $this->writeSHPOrSHXHeader(Shapefile::FILE_SHP);
        $this->writeSHPOrSHXHeader(Shapefile::FILE_SHX);
        $this->writeDBFHeader();
        $this->writeDBTHeader();
        
        // Write PRJ
        if ($this->isFileOpen(Shapefile::FILE_PRJ) && $this->getPRJ() !== null) {
            $this->writeString(Shapefile::FILE_PRJ, $this->getPRJ());
        }
        
        // Write CPG
        if ($this->isFileOpen(Shapefile::FILE_CPG) && ($this->getCharset() !== Shapefile::DBF_DEFAULT_CHARSET || $this->getOption(Shapefile::OPTION_CPG_ENABLE_FOR_DEFAULT_CHARSET))) {
            $this->writeString(Shapefile::FILE_CPG, $this->getCharset());
        }
        
        // Close files and delete empty ones
        $this->closeFiles();
        if ($this->getOption(Shapefile::OPTION_DELETE_EMPTY_FILES)) {
            foreach ($this->filenames as $filename) {
                if (filesize($filename) === 0) {
                    unlink($filename);
                }
            }
        }
    }
    
    
    public function setShapeType($type)
    {
        parent::setShapeType($type);
    }
    
    public function setCustomBoundingBox($bounding_box)
    {
        parent::setCustomBoundingBox($bounding_box);
    }
    
    public function resetCustomBoundingBox()
    {
        parent::resetCustomBoundingBox();
    }
    
    public function setPRJ($prj)
    {
        parent::setPRJ($prj);
    }
    
    public function setCharset($charset)
    {
        parent::setCharset($charset);
    }
    
    public function addField($name, $type, $size, $decimals, $flag_sanitize_name = true)
    {
        return parent::addField($name, $type, $size, $decimals, $flag_sanitize_name);
    }
    
    /**
     * Adds a char field to the Shapefile definition.
     * Returns the effective field name after eventual sanitization.
     * 
     * @param   string  $name               Name of the field. Maximum 10 characters.
     *                                      Only letters, numbers and underscores are allowed.
     * @param   integer $size               Lenght of the field, between 1 and 254 characters. Defaults to 254.
     * @param   bool    $flag_sanitize_name Optional flag to automatically replace illegal characters
     *                                      in the name with underscores. Defaults to true.
     *
     * @return  string
     */
    public function addCharField($name, $size = 254, $flag_sanitize_name = true)
    {
        return $this->addField($name, Shapefile::DBF_TYPE_CHAR, $size, 0, $flag_sanitize_name);
    }
    
    /**
     * Adds a date field to the Shapefile definition.
     * Returns the effective field name after eventual sanitization.
     * 
     * @param   string  $name               Name of the field. Maximum 10 characters.
     *                                      Only letters, numbers and underscores are allowed.
     * @param   bool    $flag_sanitize_name Optional flag to automatically replace illegal characters
     *                                      in the name with underscores. Defaults to true.
     *
     * @return  string
     */
    public function addDateField($name, $flag_sanitize_name = true)
    {
        return $this->addField($name, Shapefile::DBF_TYPE_DATE, 8, 0, $flag_sanitize_name);
    }
    
    /**
     * Adds a logical/boolean field to the Shapefile definition.
     * Returns the effective field name after eventual sanitization.
     * 
     * @param   string  $name               Name of the field. Maximum 10 characters.
     *                                      Only letters, numbers and underscores are allowed.
     * @param   bool    $flag_sanitize_name Optional flag to automatically replace illegal characters
     *                                      in the name with underscores. Defaults to true.
     *
     * @return  string
     */
    public function addLogicalField($name, $flag_sanitize_name = true)
    {
        return $this->addField($name, Shapefile::DBF_TYPE_LOGICAL, 1, 0, $flag_sanitize_name);
    }
    
    /**
     * Adds a memo field to the Shapefile definition.
     * Returns the effective field name after eventual sanitization.
     * 
     * @param   string  $name               Name of the field. Maximum 10 characters.
     *                                      Only letters, numbers and underscores are allowed.
     * @param   bool    $flag_sanitize_name Optional flag to automatically replace illegal characters
     *                                      in the name with underscores. Defaults to true.
     *
     * @return  string
     */
    public function addMemoField($name, $flag_sanitize_name = true)
    {
        return $this->addField($name, Shapefile::DBF_TYPE_MEMO, 10, 0, $flag_sanitize_name);
    }
    
    /**
     * Adds numeric to the Shapefile definition.
     * Returns the effective field name after eventual sanitization.
     * 
     * @param   string  $name               Name of the field. Maximum 10 characters.
     *                                      Only letters, numbers and underscores are allowed.
     * @param   integer $size               Lenght of the field, between 1 and 254 characters. Defaults to 10.
     * @param   integer $decimals           Optional number of decimal digits. Defaults to 0.
     * @param   bool    $flag_sanitize_name Optional flag to automatically replace illegal characters
     *                                      in the name with underscores. Defaults to true.
     *
     * @return  string
     */
    public function addNumericField($name, $size = 10, $decimals = 0, $flag_sanitize_name = true)
    {
        return $this->addField($name, Shapefile::DBF_TYPE_NUMERIC, $size, $decimals, $flag_sanitize_name);
    }
    
    /**
     * Adds floating point to the Shapefile definition.
     * Returns the effective field name after eventual sanitization.
     * 
     * @param   string  $name               Name of the field. Maximum 10 characters.
     *                                      Only letters, numbers and underscores are allowed.
     * @param   integer $size               Lenght of the field, between 1 and 254 characters. Defaults to 20.
     * @param   integer $decimals           Number of decimal digits. Defaults to 10.
     * @param   bool    $flag_sanitize_name Optional flag to automatically replace illegal characters
     *                                      in the name with underscores. Defaults to true.
     *
     * @return  string
     */
    public function addFloatField($name, $size = 20, $decimals = 10, $flag_sanitize_name = true)
    {
        return $this->addField($name, Shapefile::DBF_TYPE_FLOAT, $size, $decimals, $flag_sanitize_name);
    }
    
    
    /*
     * Writes a record to the Shapefile.
     *
     * @param   Geometry    $Geometry       Geometry to write.
     */
    public function writeRecord(Geometry\Geometry $Geometry)
    {
        // Init headers
        if (!$this->flag_init_headers) {
            $this->writeNulPadding(Shapefile::FILE_SHP, Shapefile::SHP_HEADER_SIZE);
            $this->writeNulPadding(Shapefile::FILE_SHX, Shapefile::SHX_HEADER_SIZE);
            $this->writeNulPadding(Shapefile::FILE_DBF, $this->getDBFHeaderSize());
            if (in_array(Shapefile::DBF_TYPE_MEMO, $this->arrayColumn($this->getFields(), 'type'))) {
                if (!$this->isFileOpen(Shapefile::FILE_DBT)) {
                    throw new ShapefileException(Shapefile::ERR_FILE_MISSING, strtoupper(Shapefile::FILE_DBT));
                }
                $this->writeNulPadding(Shapefile::FILE_DBT, Shapefile::DBT_BLOCK_SIZE);
                ++$this->dbt_next_available_block;
            }
            $this->flag_init_headers = true;
        }
        
        // Pair with Geometry and increase records count
        $this->pairGeometry($Geometry);
        ++$this->tot_records;
        
        // Write data
        $this->writeSHPAndSHXData($Geometry);
        $this->writeDBFData($Geometry);
    }
    
    
    
    /////////////////////////////// PRIVATE ///////////////////////////////
    /**
     * Packs data according to the given format and writes it to a file.
     *
     * @param   string  $file_type          File type.
     * @param   string  $format             Format code. See php pack() documentation.
     * @param   string  $data               String value to write.
     * @param   bool    $invert_endianness  Set this optional flag to true when reading floating point numbers on a big endian machine.
     *
     * @return  mixed
     */
    private function writeData($file_type, $format, $data, $invert_endianness = false)
    {
        $data = pack($format, $data);
        if ($invert_endianness) {
            $data = strrev($data);
        }
        if ($this->fileWrite($file_type, $data) === false) {
            throw new ShapefileException(Shapefile::ERR_FILE_WRITING);
        }
    }
    
    /**
     * Writes an unsigned char from a resource handle.
     *
     * @param   string  $file_type      File type.
     * @param   integer $data           Value to write.
     */
    private function writeChar($file_type, $data)
    {
        $this->writeData($file_type, 'C', $data);
    }
    
    /**
     * Writes an unsigned short, 16 bit, little endian byte order, to a resource handle.
     *
     * @param   string  $file_type      File type.
     * @param   integer $data           Value to write.
     */
    private function writeInt16L($file_type, $data)
    {
        $this->writeData($file_type, 'v', $data);
    }
    
    /**
     * Writes an unsigned long, 32 bit, big endian byte order, to a resource handle.
     *
     * @param   string  $file_type      File type.
     * @param   integer $data           Value to write.
     */
    private function writeInt32B($file_type, $data)
    {
        $this->writeData($file_type, 'N', $data);
    }
    
    /**
     * Writes an unsigned long, 32 bit, little endian byte order, to a resource handle.
     *
     * @param   string  $file_type      File type.
     * @param   integer $data           Value to write.
     */
    private function writeInt32L($file_type, $data)
    {
        $this->writeData($file_type, 'V', $data);
    }
    
    /**
     * Writes a double, 64 bit, little endian byte order, to a resource handle.
     *
     * @param   string  $file_type      File type.
     * @param   float   $data           Value to write.
     */
    private function writeDoubleL($file_type, $data)
    {
        $this->writeData($file_type, 'd', $data, $this->isBigEndianMachine());
    }
    
    /**
     * Writes a string to a resource handle.
     *
     * @param   string  $file_type      File type.
     * @param   string  $data           Value to write.
     */
    private function writeString($file_type, $data)
    {
        $this->writeData($file_type, 'A*', $data);
    }
    
    /**
     * Writes a NUL-padding of given length to a resource handle.
     *
     * @param   string  $file_type      File type.
     * @param   string  $lenght         Length of the padding to write.
     */
    private function writeNulPadding($file_type, $lenght)
    {
        $this->writeData($file_type, 'a*', str_repeat("\0", $lenght));
    }
        
    
    /**
     * Writes some XY coordinates to the Shapefile.
     *
     * @param   array   $coordinates    Array with "x" and "y" coordinates.
     */
    private function writeXY($coordinates)
    {
        $this->writeDoubleL(Shapefile::FILE_SHP, $coordinates['x']);
        $this->writeDoubleL(Shapefile::FILE_SHP, $coordinates['y']);
    }
    
    /**
     * Writes a Z coordinate to the Shapefile.
     *
     * @param   array   $coordinates    Array with "z" coordinate.
     */
    private function writeZ($coordinates)
    {
        $this->writeDoubleL(Shapefile::FILE_SHP, $this->getOption(Shapefile::OPTION_SUPPRESS_Z) ? 0 : $coordinates['z']);
    }
    
    /**
     * Writes an M coordinate to the Shapefile.
     *
     * @param   array   $coordinates    Array with "m" coordinate.
     */
    private function writeM($coordinates)
    {
        $this->writeDoubleL(Shapefile::FILE_SHP, $this->getOption(Shapefile::OPTION_SUPPRESS_M) ? 0 : $this->parseM($coordinates['m']));
    }
    
    /**
     * Parses an M coordinate according to the ESRI specs:
     * «Any floating point number smaller than –10^38 is considered by a shapefile reader to represent a "no data" value»
     *
     * @return  float|bool
     */
    private function parseM($value)
    {
        return ($value === false) ? Shapefile::SHP_NO_DATA_VALUE : $value;
    }
    
    
    /**
     * Writes an XY bounding box into a file.
     *
     * @param   string  $file_type      File type.
     * @param   array   $bounding_box   Associative array with xmin, xmax, ymin, ymax values.
     */
    private function writeXYBoundingBox($file_type, $bounding_box)
    {
        $this->writeDoubleL($file_type, $bounding_box['xmin']);
        $this->writeDoubleL($file_type, $bounding_box['ymin']);
        $this->writeDoubleL($file_type, $bounding_box['xmax']);
        $this->writeDoubleL($file_type, $bounding_box['ymax']);
    }
    
    /**
     * Writes a Z range into a file.
     *
     * @param   string  $file_type      File type.
     * @param   array   $bounding_box   Associative array with zmin and zmax values.
     */
    private function writeZRange($file_type, $bounding_box)
    {
        $this->writeDoubleL($file_type, $this->getOption(Shapefile::OPTION_SUPPRESS_Z) ? 0 : $bounding_box['zmin']);
        $this->writeDoubleL($file_type, $this->getOption(Shapefile::OPTION_SUPPRESS_Z) ? 0 : $bounding_box['zmax']);
    }
    
    /**
     * Writes an M range into a file.
     *
     * @param   string  $file_type      File type.
     * @param   array   $bounding_box   Associative array with mmin and mmax values.
     */
    private function writeMRange($file_type, $bounding_box)
    {
        $this->writeDoubleL($file_type, $this->getOption(Shapefile::OPTION_SUPPRESS_M) ? 0 : $this->parseM($bounding_box['mmin']));
        $this->writeDoubleL($file_type, $this->getOption(Shapefile::OPTION_SUPPRESS_M) ? 0 : $this->parseM($bounding_box['mmax']));
    }
    
    
    /**
     * Writes a Null shape to the Shapefile.
     */
    private function writeNull()
    {
        // Shape type
        $this->writeInt32L(Shapefile::FILE_SHP, Shapefile::SHAPE_TYPE_NULL);
    }
    
    
    /**
     * Writes a Point shape to the Shapefile.
     *
     * @param   array   $coordinates    Array with "x" and "y" coordinates.
     * @param   string  $shape_type     Optional shape type to write in the record.
     */
    private function writePoint($coordinates, $shape_type = Shapefile::SHAPE_TYPE_POINT)
    {
        // Shape type
        $this->writeInt32L(Shapefile::FILE_SHP, $shape_type);
        // XY Coordinates
        $this->writeXY($coordinates);
    }
    
    /**
     * Writes a PointM shape to the Shapefile.
     *
     * @param   array   $coordinates    Array with "m" coordinate.
     */
    private function writePointM($coordinates)
    {
        // XY Point
        $this->writePoint($coordinates, Shapefile::SHAPE_TYPE_POINTM);
        // M Coordinate
        $this->writeM($coordinates);
    }
    
    /**
     * Writes a PointZ shape to the Shapefile.
     *
     * @param   array   $coordinates    Array with "z" coordinate.
     */
    private function writePointZ($coordinates)
    {
        // XY Point
        $this->writePoint($coordinates, Shapefile::SHAPE_TYPE_POINTZ);
        // Z Coordinate
        $this->writeZ($coordinates);
        // M Coordinate
        $this->writeM($coordinates);
    }
    
    
    /**
     * Writes a MultiPoint shape to the Shapefile.
     *
     * @param   array   $array          Array with "numpoints" and "points" elements.
     
     * @param   string  $shape_type     Optional shape type to write in the record.
     * @param   array   $bounding_box   Associative array with xmin, xmax, ymin, ymax values.
     */
    private function writeMultiPoint($array, $bounding_box, $shape_type = Shapefile::SHAPE_TYPE_MULTIPOINT)
    {
        // Shape type
        $this->writeInt32L(Shapefile::FILE_SHP, $shape_type);
        // XY Bounding Box
        $this->writeXYBoundingBox(Shapefile::FILE_SHP, $bounding_box);
        // NumPoints
        $this->writeInt32L(Shapefile::FILE_SHP, $array['numpoints']);
        // Points
        foreach ($array['points'] as $coordinates) {
            $this->writeXY($coordinates);
        }
    }
    
    /**
     * Writes a MultiPointM shape to the Shapefile.
     *
     * @param   array   $array          Array with "numpoints" and "points" elements.
     * @param   array   $bounding_box   Associative array with xmin, xmax, ymin, ymax, mmin, mmax values.
     */
    private function writeMultiPointM($array, $bounding_box)
    {
        // XY MultiPoint
        $this->writeMultiPoint($array, $bounding_box, Shapefile::SHAPE_TYPE_MULTIPOINTM);
        // M Range
        $this->writeMRange(Shapefile::FILE_SHP, $bounding_box);
        // M Array
        foreach ($array['points'] as $coordinates) {
            $this->writeM($coordinates);
        }
    }
    
    /**
     * Writes a MultiPointZ shape to the Shapefile.
     *
     * @param   array   $array          Array with "numpoints" and "points" elements.
     * @param   array   $bounding_box   Associative array with xmin, xmax, ymin, ymax, zmin, zmax, mmin, mmax values.
     */
    private function writeMultiPointZ($array, $bounding_box)
    {
        // XY MultiPoint
        $this->writeMultiPoint($array, $bounding_box, Shapefile::SHAPE_TYPE_MULTIPOINTZ);
        // Z Range
        $this->writeZRange(Shapefile::FILE_SHP, $bounding_box);
        // Z Array
        foreach ($array['points'] as $coordinates) {
            $this->writeZ($coordinates);
        }
        // M Range
        $this->writeMRange(Shapefile::FILE_SHP, $bounding_box);
        // M Array
        foreach ($array['points'] as $coordinates) {
            $this->writeM($coordinates);
        }
    }
    
    
    /**
     * Writes a PolyLine shape to the Shapefile.
     *
     * @param   array   $array          Array with "numparts" and "parts" elements.
     * @param   array   $bounding_box   Associative array with xmin, xmax, ymin, ymax values.
     * @param   string  $shape_type     Optional shape type to write in the record.
     */
    private function writePolyLine($array, $bounding_box, $shape_type = Shapefile::SHAPE_TYPE_POLYLINE)
    {
        // Shape type
        $this->writeInt32L(Shapefile::FILE_SHP, $shape_type);
        // XY Bounding Box
        $this->writeXYBoundingBox(Shapefile::FILE_SHP, $bounding_box);
        // NumParts
        $this->writeInt32L(Shapefile::FILE_SHP, $array['numparts']);
        // NumPoints
        $this->writeInt32L(Shapefile::FILE_SHP, array_sum($this->arrayColumn($array['parts'], 'numpoints')));
        // Parts
        $part_first_index = 0;
        foreach ($array['parts'] as $part) {
            $this->writeInt32L(Shapefile::FILE_SHP, $part_first_index);
            $part_first_index += $part['numpoints'];
        }
        // Points
        foreach ($array['parts'] as $part) {
            foreach ($part['points'] as $coordinates) {
                $this->writeXY($coordinates);
            }
        }
    }
    
    /**
     * Writes a PolyLineM shape to the Shapefile.
     *
     * @param   array   $array          Array with "numparts" and "parts" elements.
     * @param   array   $bounding_box   Associative array with xmin, xmax, ymin, ymax, mmin, mmax values.
     * @param   string  $shape_type     Optional shape type to write in the record.
     */
    private function writePolyLineM($array, $bounding_box, $shape_type = Shapefile::SHAPE_TYPE_POLYLINEM)
    {
        // XY PolyLine
        $this->writePolyLine($array, $bounding_box, $shape_type);
        // M Range
        $this->writeMRange(Shapefile::FILE_SHP, $bounding_box);
        // M Array
        foreach ($array['parts'] as $part) {
            foreach ($part['points'] as $coordinates) {
                $this->writeM($coordinates);
            }
        }
    }
    
    /**
     * Writes a PolyLineZ shape to the Shapefile.
     *
     * @param   array   $array          Array with "numparts" and "parts" elements.
     * @param   array   $bounding_box   Associative array with xmin, xmax, ymin, ymax, zmin, zmax, mmin, mmax values.
     * @param   string  $shape_type     Optional shape type to write in the record.
     */
    private function writePolyLineZ($array, $bounding_box, $shape_type = Shapefile::SHAPE_TYPE_POLYLINEZ)
    {
        // XY PolyLine
        $this->writePolyLine($array, $bounding_box, $shape_type);
        // Z Range
        $this->writeZRange(Shapefile::FILE_SHP, $bounding_box);
        // Z Array
        foreach ($array['parts'] as $part) {
            foreach ($part['points'] as $coordinates) {
                $this->writeZ($coordinates);
            }
        }
         // M Range
        $this->writeMRange(Shapefile::FILE_SHP, $bounding_box);
        // M Array
        foreach ($array['parts'] as $part) {
            foreach ($part['points'] as $coordinates) {
                $this->writeM($coordinates);
            }
        }
    }
    
    
    /**
     * Writes a Polygon shape to the Shapefile.
     *
     * @param   array   $array          Array with "numparts" and "parts" elements.
     * @param   array   $bounding_box   Associative array with xmin, xmax, ymin, ymax values.
     */
    private function writePolygon($array, $bounding_box)
    {
        $this->writePolyLine($this->parsePolygon($array), $bounding_box, Shapefile::SHAPE_TYPE_POLYGON);
    }
    
    /**
     * Writes a PolygonM shape to the Shapefile.
     *
     * @param   array   $array          Array with "numparts" and "parts" elements.
     * @param   array   $bounding_box   Associative array with xmin, xmax, ymin, ymax, mmin, mmax values.
     */
    private function writePolygonM($array, $bounding_box)
    {
        $this->writePolyLineM($this->parsePolygon($array), $bounding_box, Shapefile::SHAPE_TYPE_POLYGONM);
    }
    
    /**
     * Writes a PolygonZ shape to the Shapefile.
     *
     * @param   array   $array          Array with "numparts" and "parts" elements.
     * @param   array   $bounding_box   Associative array with xmin, xmax, ymin, ymax, zmin, zmax, mmin, mmax values.
     */
    private function writePolygonZ($array, $bounding_box)
    {
        $this->writePolyLineZ($this->parsePolygon($array), $bounding_box, Shapefile::SHAPE_TYPE_POLYGONZ);
    }
    
    /**
     * Parses a Polygon array (with multiple "rings" for each "part") into a plain structure of sequential parts
     * enforcing outer and innner rings orientation.
     *
     * @param   array   $array      Array "parts" elements.
     *
     * @return  array
     */
    private function parsePolygon($array)
    {
        $parts = [];
        foreach ($array['parts'] as $part) {
            foreach ($part['rings'] as $i => $ring) {
                // Enforce clockwise outer rings and counterclockwise inner rings
                $is_clockwise = $this->isClockwise($ring['points']);
                if (($i == 0 && !$is_clockwise) || ($i > 0 && $is_clockwise)) {
                    $ring['points'] = array_reverse($ring['points']);
                }
                $parts[] = $ring;
            }
        }
        
        return [
            'numparts'  => count($parts),
            'parts'     => $parts,
        ];
    }
    
    
    /**
     * Writes SHP or SHX file header.
     *
     * @param   string  $file_type      File type.
     */
    private function writeSHPOrSHXHeader($file_type)
    {
        $this->setFilePointer($file_type, 0);
        
        // File Code
        $this->writeInt32B($file_type, Shapefile::SHP_FILE_CODE);
        
        // Unused bytes
        $this->setFileOffset($file_type, 20);
        
        // File Length
        $this->writeInt32B($file_type, $this->getFileSize($file_type) / 2);
        
        // Version
        $this->writeInt32L($file_type, Shapefile::SHP_VERSION);
        
        // Shape Type
        $this->writeInt32L($file_type, $this->getShapeType(Shapefile::FORMAT_INT));
        
        //Bounding Box
        $bounding_box = $this->getBoundingBox();
        $this->writeXYBoundingBox($file_type, $bounding_box);
        $this->writeZRange($file_type, $this->isZ() ? $bounding_box : ['zmin' => 0, 'zmax' => 0]);
        $this->writeMRange($file_type, $this->isM() ? $bounding_box : ['mmin' => 0, 'mmax' => 0]);
        
        $this->resetFilePointer($file_type);
    }
    
    /**
     * Writes DBF file header.
     */
    private function writeDBFHeader()
    {
        $this->setFilePointer(Shapefile::FILE_DBF, 0);
        
        // Version number
        $this->writeChar(Shapefile::FILE_DBF, $this->dbt_next_available_block > 0 ? Shapefile::DBF_VERSION_WITH_DBT : Shapefile::DBF_VERSION);
        
        // Date of last update
        $this->writeChar(Shapefile::FILE_DBF, intval(date('Y')) - 1900);
        $this->writeChar(Shapefile::FILE_DBF, intval(date('m')));
        $this->writeChar(Shapefile::FILE_DBF, intval(date('d')));
        
        // Number of records
        $this->writeInt32L(Shapefile::FILE_DBF, $this->tot_records);

        // Header size
        $this->writeInt16L(Shapefile::FILE_DBF, $this->getDBFHeaderSize());
        
         // Record size
        $this->writeInt16L(Shapefile::FILE_DBF, $this->getDBFRecordSize());
        
        // Reserved bytes
        $this->setFileOffset(Shapefile::FILE_DBF, 20);
        
        // Field descriptor array
        foreach ($this->getFields() as $name => $field) {
            // Name
            $this->writeString(Shapefile::FILE_DBF, str_pad($name, 10, "\0", STR_PAD_RIGHT));
            $this->setFileOffset(Shapefile::FILE_DBF, 1);
            // Type
            $this->writeString(Shapefile::FILE_DBF, $field['type']);
            $this->setFileOffset(Shapefile::FILE_DBF, 4);
            // Size
            $this->writeChar(Shapefile::FILE_DBF, $field['size']);
            // Decimals
            $this->writeChar(Shapefile::FILE_DBF, $field['decimals']);
            $this->setFileOffset(Shapefile::FILE_DBF, 14);
        }
        
        // Field terminator
        $this->writeChar(Shapefile::FILE_DBF, Shapefile::DBF_FIELD_TERMINATOR);
        
        // EOF Marker
        $this->resetFilePointer(Shapefile::FILE_DBF);
        $this->writeChar(Shapefile::FILE_DBF, Shapefile::DBF_EOF_MARKER);
    }
    
    /**
     * Writes DBT file header.
     */
    private function writeDBTHeader()
    {
        if ($this->dbt_next_available_block === 0) {
            return;
        }
        
        $this->setFilePointer(Shapefile::FILE_DBT, 0);
        
        // Next available block 
        $this->writeInt32L(Shapefile::FILE_DBT, $this->dbt_next_available_block);
        
        // Reserved bytes
        $this->setFileOffset(Shapefile::FILE_DBT, 12);
        
        // Version number
        $this->writeChar(Shapefile::FILE_DBT, Shapefile::DBF_VERSION);
        
        $this->resetFilePointer(Shapefile::FILE_DBT);
    }
    
    /**
     * Computes DBF header size.
     * 32bytes + (number of fields x 32) + 1 (field terminator character)
     */
    private function getDBFHeaderSize()
    {
        return 33 + (32 * count($this->getFields()));
    }
    
    /**
     * Computes DBF record size.
     * Sum of all fields sizes + 1 (record deleted flag).
     */
    private function getDBFRecordSize()
    {
        return array_sum($this->arrayColumn($this->getFields(), 'size')) + 1;
    }
    
    
    /*
     * Writes Geometry data to SHP and SHX files.
     *
     * @param   Geometry    $Geometry   Geometry to write.
     */
    private function writeSHPAndSHXData(Geometry\Geometry $Geometry)
    {
        // === SHP ===
        $array          = [];
        $write_method   = 'writeNull';
        if (!$Geometry->isEmpty()) {
            $class = get_class($Geometry);
            $array = $Geometry->getArray();
            if ($class == 'Shapefile\Geometry\Linestring' || $class == 'Shapefile\Geometry\Polygon') {
                $array = [
                    'numparts'  => 1,
                    'parts'     => [$array],
                ];
            }
            $methods = [
                'Shapefile\Geometry\Point'              => 'writePoint',
                'Shapefile\Geometry\MultiPoint'         => 'writeMultiPoint',
                'Shapefile\Geometry\Linestring'         => 'writePolyLine',
                'Shapefile\Geometry\MultiLinestring'    => 'writePolyLine',
                'Shapefile\Geometry\Polygon'            => 'writePolygon',
                'Shapefile\Geometry\MultiPolygon'       => 'writePolygon',
            ];
            $write_method = $methods[$class];
            if ($Geometry->isZ()) {
                $write_method .= 'Z';
            } elseif ($Geometry->isM()) {
                $write_method .= 'M';
            }
        }
        // Save current offset and leave space for record header
        $old_shp_offset = $this->getFilePointer(Shapefile::FILE_SHP);
        $this->setFileOffset(Shapefile::FILE_SHP, 8);
        // Write Geometry
        $this->{$write_method}($array, $Geometry->getBoundingBox());
        // Update record header
        $shp_content_length = (($this->getFilePointer(Shapefile::FILE_SHP) - $old_shp_offset) / 2) - 4;
        $this->setFilePointer(Shapefile::FILE_SHP, $old_shp_offset);
        $this->writeInt32B(Shapefile::FILE_SHP, $this->tot_records);
        $this->writeInt32B(Shapefile::FILE_SHP, $shp_content_length);
        $this->resetFilePointer(Shapefile::FILE_SHP);
        
        // === SHX ===
        // Offset (in 16bit words)
        $this->writeInt32B(Shapefile::FILE_SHX, $old_shp_offset / 2);
        // Content Length (in 16bit words)
        $this->writeInt32B(Shapefile::FILE_SHX, $shp_content_length);
    }
    
    /*
     * Writes Geometry data to DBF file.
     *
     * @param   Geometry    $Geometry   Geometry to write.
     */
    private function writeDBFData(Geometry\Geometry $Geometry)
    {
        // === DBF ===
        // Deleted flag
        $this->writeChar(Shapefile::FILE_DBF, $Geometry->isDeleted() ? Shapefile::DBF_DELETED_MARKER : Shapefile::DBF_BLANK);
        // Data
        $data = $Geometry->getDataArray();
        if ($this->getOption(Shapefile::OPTION_DBF_FORCE_ALL_CAPS)) {
            $data = array_change_key_case($data, CASE_UPPER);
        }
        foreach ($this->getFields() as $name => $field) {
            if (!isset($data[$name])) {
                if ($this->getOption(Shapefile::OPTION_ENFORCE_GEOMETRY_DATA_STRUCTURE)) {
                    throw new ShapefileException(Shapefile::ERR_GEOM_MISSING_FIELD, $name);
                }
                $data[$name] = null;
            }
            $value = $this->encodeFieldValue($field['type'], $field['size'], $field['decimals'], $data[$name]);
            // Memo (DBT)
            if ($field['type'] === Shapefile::DBF_TYPE_MEMO && $value !== null) {
                $value = $this->writeDBTData($value, $field['size']);
            }
            // Null
            if ($value === null) {
                $value = str_repeat(($this->getOption(Shapefile::OPTION_DBF_NULL_PADDING_CHAR) !== null ? $this->getOption(Shapefile::OPTION_DBF_NULL_PADDING_CHAR) : chr(Shapefile::DBF_BLANK)), $field['size']);
            }
            // Write value to file
            $this->writeString(Shapefile::FILE_DBF, $value);
        }
    }
    
    /*
     * Writes data to DBT file and returns first block number.
     *
     * @param   string  $data       Data to write
     * @param   integer $field_size Size of the DBF field.
     *
     * @return  string
     */
    private function writeDBTData($data, $field_size)
    {
        // Ignore empty values
        if ($data === '') {
            return str_repeat(chr(Shapefile::DBF_BLANK), $field_size);
        }
        
        // Block number to return
        $ret = str_pad($this->dbt_next_available_block, $field_size, chr(Shapefile::DBF_BLANK), STR_PAD_LEFT);
        
        // Corner case: there's not enough space at the end of the last block for 2 field terminators. Add a space and switch to the next block!
        if (strlen($data) % Shapefile::DBT_BLOCK_SIZE == Shapefile::DBT_BLOCK_SIZE - 1) {
            $data .= chr(Shapefile::DBF_BLANK);
        }
        // Add TWO field terminators
        $data .= str_repeat(chr(Shapefile::DBT_FIELD_TERMINATOR), 2);
        // Write data
        foreach (str_split($data, Shapefile::DBT_BLOCK_SIZE) as $block) {
            $this->writeString(Shapefile::FILE_DBT, str_pad($block, Shapefile::DBT_BLOCK_SIZE, "\0", STR_PAD_RIGHT));
            ++$this->dbt_next_available_block; 
        }
        
        return $ret;
    }
    
    /**
     * Encodes a value to be written into a DBF field as a raw string.
     *
     * @param   string  $type       Type of the field.
     * @param   integer $size       Lenght of the field.
     * @param   integer $decimals   Number of decimal digits for numeric type.
     * @param   string  $value      Value to encode.
     *
     * @return  string|null
     */
    private function encodeFieldValue($type, $size, $decimals, $value)
    {
        switch ($type) {
            case Shapefile::DBF_TYPE_CHAR:
                if ($value !== null) {
                    $value = $this->truncateOrPadString($value, $size);
                }
                break;
            
            case Shapefile::DBF_TYPE_DATE:
                if (is_a($value, 'DateTime')) {
                    $value = $value->format('Ymd');
                } elseif ($value !== null) {
                    // Try YYYY-MM-DD format
                    $DateTime   = \DateTime::createFromFormat('Y-m-d', $value);
                    $errors     = \DateTime::getLastErrors();
                    if ($errors['warning_count'] || $errors['error_count']) {
                        // Try YYYYMMDD format
                        $DateTime   = \DateTime::createFromFormat('Ymd', $value);
                        $errors     = \DateTime::getLastErrors();
                    }
                    if ($errors['warning_count'] || $errors['error_count']) {
                        $value = $this->getOption(Shapefile::OPTION_DBF_NULLIFY_INVALID_DATES) ? null : $this->truncateOrPadString($this->sanitizeNumber($value), $size);
                    } else {
                        $value = $DateTime->format('Ymd');
                    }
                }
                break;
            
            case Shapefile::DBF_TYPE_LOGICAL:
                if ($value === null) {
                    $value = '?';
                } elseif ($value === true || in_array(substr(trim($value), 0, 1), ['Y', 'y', 'T', 't'])) {
                    $value = 'T';
                } else {
                    $value = 'F';
                }
                break;
            
            case Shapefile::DBF_TYPE_MEMO:
                if ($value !== null) {
                    $value = (string) $value;
                }
                break;
            
            case Shapefile::DBF_TYPE_NUMERIC:
            case Shapefile::DBF_TYPE_FLOAT:
                if ($value !== null) {
                    $value          = trim($value);
                    $flag_negative  = substr($value, 0, 1) === '-';
                    $intpart        = $this->sanitizeNumber(strpos($value, '.') === false ? $value : strstr($value, '.', true));
                    $decpart        = $this->sanitizeNumber(substr(strstr($value, '.', false), 1));
                    $decpart        = strlen($decpart) > $decimals ? substr($decpart, 0, $decimals) : str_pad($decpart, $decimals, '0', STR_PAD_RIGHT);
                    $value          = ($flag_negative ? '-' : '') . $intpart . ($decimals > 0 ? '.' : '') . $decpart;
                    if (strlen($value) > $size) {
                        throw new ShapefileException(Shapefile::ERR_INPUT_NUMERIC_VALUE_OVERFLOW, "value:$intpart - size:($size.$decimals)");
                    }
                    $value = str_pad($value, $size, chr(Shapefile::DBF_BLANK), STR_PAD_LEFT);
                }
                break;
        }
        
        return $value;
    }
    
    /**
     * Truncates long input strings and right-pads short ones to maximum/minimun lenght.
     *
     * @param   string  $value      Value to pad.
     * @param   integer $size       Lenght of the field.
     *
     * @return  string
     */
    private function truncateOrPadString($value, $size)
    {
        return str_pad(substr($value, 0, $size), $size, chr(Shapefile::DBF_BLANK), STR_PAD_RIGHT);
    }
    
    /**
     * Removes illegal characters from a numeric string.
     *
     * @param   string  $value      Value to sanitize.
     *
     * @return  string
     */
    private function sanitizeNumber($value)
    {
        return preg_replace('/[^0-9]/', '', $value);
    }
    
    
    /**
     * Substitute for PHP 5.5 array_column() function.
     *
     * @param   array   $array      Multidimensional array.
     * @param   string  $key        Key of the column to return.
     *
     * @return  array
     */
    private function arrayColumn($array, $key)
    {
        return array_map(function($element) use ($key){
            return $element[$key];
        }, $array);
    }
    
}
