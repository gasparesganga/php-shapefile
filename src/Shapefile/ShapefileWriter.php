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

namespace Shapefile;

/**
 * ShapefileWriter class.
 */
class ShapefileWriter extends Shapefile
{
    /** SHP pack methods hash */
    private static $shp_pack_methods = [
        Shapefile::SHAPE_TYPE_NULL          => 'packNull',
        Shapefile::SHAPE_TYPE_POINT         => 'packPoint',
        Shapefile::SHAPE_TYPE_POLYLINE      => 'packPolyLine',
        Shapefile::SHAPE_TYPE_POLYGON       => 'packPolygon',
        Shapefile::SHAPE_TYPE_MULTIPOINT    => 'packMultiPoint',
    ];
    
    /** Buffered file types */
    private static $buffered_files = [
        Shapefile::FILE_SHP,
        Shapefile::FILE_SHX,
        Shapefile::FILE_DBF,
        Shapefile::FILE_DBT,
    ];
    
    
    /**
     * @var array   File writing buffers.
     */
    private $buffers = [];
    
    /**
     * @var int     Buffered records count.
     */
    private $buffered_record_count = 0;
     
    /**
     * @var int     Current offset in SHP file and buffer (in 16-bit words).
     *              First 50 16-bit are reserved for file header.
     */
    private $shp_current_offset = 50;
    
    /**
     * @var int     Next available block in DBT file.
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
            Shapefile::OPTION_BUFFERED_RECORDS,
            Shapefile::OPTION_CPG_ENABLE_FOR_DEFAULT_CHARSET,
            Shapefile::OPTION_DBF_ALLOW_FIELD_SIZE_255,
            Shapefile::OPTION_DBF_FORCE_ALL_CAPS,
            Shapefile::OPTION_DBF_NULL_PADDING_CHAR,
            Shapefile::OPTION_DBF_NULLIFY_INVALID_DATES,
            Shapefile::OPTION_DELETE_EMPTY_FILES,
            Shapefile::OPTION_ENFORCE_GEOMETRY_DATA_STRUCTURE,
            Shapefile::OPTION_EXISTING_FILES_MODE,
            Shapefile::OPTION_SUPPRESS_M,
            Shapefile::OPTION_SUPPRESS_Z,
        ], $options);
        
        // Open files
        $this->openFiles($files, true);
        
        // Init Buffers
        $this->buffers = array_fill_keys(array_intersect(self::$buffered_files, array_keys($this->getFiles())), '');
        
        // Mode overwrite
        if ($this->getOption(Shapefile::OPTION_EXISTING_FILES_MODE) === Shapefile::MODE_OVERWRITE) {
            $this->truncateFiles();
        }
        
        // Mode append
        if ($this->getOption(Shapefile::OPTION_EXISTING_FILES_MODE) === Shapefile::MODE_APPEND && $this->getFileSize(Shapefile::FILE_SHP) > 0) {
            // Open Shapefile in reading mode
            $ShapefileReader = new ShapefileReader($this->getFiles(), [
                Shapefile::OPTION_DBF_CONVERT_TO_UTF8   => false,
                Shapefile::OPTION_DBF_FORCE_ALL_CAPS    => $this->getOption(Shapefile::OPTION_DBF_FORCE_ALL_CAPS),
                Shapefile::OPTION_DBF_IGNORED_FIELDS    => [],
                Shapefile::OPTION_IGNORE_SHAPEFILE_BBOX => false,
            ]);
            // Shape type
            $this->setShapeType($ShapefileReader->getShapeType(Shapefile::FORMAT_INT));
            // PRJ
            $this->setPRJ($ShapefileReader->getPRJ());
            // Charset
            $this->setCharset($ShapefileReader->getCharset());
            // Bounding Box
            $this->overwriteComputedBoundingBox($ShapefileReader->getBoundingBox());
            // Fields
            foreach ($ShapefileReader->getFields() as $name => $field) {
                $this->addField($name, $field['type'], $field['size'], $field['decimals']);
            }
            // Next DBT available block
            if ($this->isFileOpen(Shapefile::FILE_DBT) && $this->getFileSize(Shapefile::FILE_DBT) > 0) {
                $this->dbt_next_available_block = ($this->getFileSize(Shapefile::FILE_DBT) / Shapefile::DBT_BLOCK_SIZE);
            }
            // Number of records
            $this->setTotRecords($ShapefileReader->getTotRecords());
            // Close Shapefile in reading mode
            $ShapefileReader = null;
            
            if ($this->getTotRecords() > 0) {
                // Mark Shapefile as initialized
                $this->setFlagInitialized(true);
                // Set flag init headers
                $this->flag_init_headers = true;
                // SHP current offset (in 16-bit words)
                $this->shp_current_offset = $this->getFileSize(Shapefile::FILE_SHP) / 2;
                // Remove DBF EOF marker
                $dbf_size_without_eof = $this->getFileSize(Shapefile::FILE_DBF) - 1;
                $this->setFilePointer(Shapefile::FILE_DBF, $dbf_size_without_eof);
                if ($this->readData(Shapefile::FILE_DBF, 1) === $this->packChar(Shapefile::DBF_EOF_MARKER)) {
                    $this->fileTruncate(Shapefile::FILE_DBF, $dbf_size_without_eof);
                }
                // Reset pointers
                foreach (array_keys($this->getFiles()) as $file_type) {
                    $this->resetFilePointer($file_type);
                }
            } else {
                $this->truncateFiles();
            }
        }
    }
    
    /**
     * Destructor.
     *
     * Finalizes open files.
     * If files were NOT passed as stream resources, empty useless files will be removed.
     */
    public function __destruct()
    {
        // Flush buffers
        $this->writeBuffers();
        
        // Try setting Shapefile as NULL SHAPE if it hasn't been initialized yet (no records written)
        if (!$this->isInitialized()) {
            try {
                $this->setShapeType(Shapefile::SHAPE_TYPE_NULL);
            } catch (ShapefileException $e) {
                // Nothing.
            }
        }
        
        // Set buffered file pointers to beginning of files
        foreach (array_keys($this->buffers) as $file_type) {
            $this->setFilePointer($file_type, 0);
        }
        // Write SHP, SHX, DBF and DBT headers to buffers
        $this->bufferData(Shapefile::FILE_SHP, $this->packSHPOrSHXHeader($this->getFileSize(Shapefile::FILE_SHP)));
        $this->bufferData(Shapefile::FILE_SHX, $this->packSHPOrSHXHeader($this->getFileSize(Shapefile::FILE_SHX)));
        $this->bufferData(Shapefile::FILE_DBF, $this->packDBFHeader());
        if ($this->dbt_next_available_block > 0) {
            $this->bufferData(Shapefile::FILE_DBT, $this->packDBTHeader());
        }
        // Write buffers containing the headers
        $this->writeBuffers();
        // Reset buffered file pointers
        foreach (array_keys($this->buffers) as $file_type) {
            $this->resetFilePointer($file_type);
        }
        
        // Write DBF EOF marker
        $this->writeData(Shapefile::FILE_DBF, $this->packChar(Shapefile::DBF_EOF_MARKER));
        
        // Write PRJ file
        if ($this->isFileOpen(Shapefile::FILE_PRJ)) {
            $this->fileTruncate(Shapefile::FILE_PRJ);
            $this->writeData(Shapefile::FILE_PRJ, $this->packString($this->getPRJ()));
        }
        
        // Write CPG file
        if ($this->isFileOpen(Shapefile::FILE_CPG)) {
            $this->fileTruncate(Shapefile::FILE_CPG);
            if ($this->getCharset() !== Shapefile::DBF_DEFAULT_CHARSET || $this->getOption(Shapefile::OPTION_CPG_ENABLE_FOR_DEFAULT_CHARSET)) {
                $this->writeData(Shapefile::FILE_CPG, $this->packString($this->getCharset()));
            }
        }
        
        // Close files and delete empty ones
        $this->closeFiles();
        if ($this->getOption(Shapefile::OPTION_DELETE_EMPTY_FILES)) {
            foreach ($this->getFilenames() as $filename) {
                if (filesize($filename) === 0) {
                    unlink($filename);
                }
            }
        }
    }
    
    
    public function setShapeType($type)
    {
        return parent::setShapeType($type);
    }
    
    public function setCustomBoundingBox($bounding_box)
    {
        return parent::setCustomBoundingBox($bounding_box);
    }
    
    public function resetCustomBoundingBox()
    {
        return parent::resetCustomBoundingBox();
    }
    
    public function setPRJ($prj)
    {
        return parent::setPRJ($prj);
    }
    
    
    public function addField($name, $type, $size, $decimals)
    {
        return parent::addField($name, $type, $size, $decimals);
    }
    
    /**
     * Adds a char field to the Shapefile definition.
     * Returns the effective field name after eventual sanitization.
     *
     * @param   string  $name               Name of the field. Invalid names will be sanitized
     *                                      (maximum 10 characters, only letters, numbers and underscores are allowed).
     *                                      Only letters, numbers and underscores are allowed.
     * @param   int     $size               Lenght of the field, between 1 and 254 characters. Defaults to 254.
     *
     * @return  string
     */
    public function addCharField($name, $size = 254)
    {
        return $this->addField($name, Shapefile::DBF_TYPE_CHAR, $size, 0);
    }
    
    /**
     * Adds a date field to the Shapefile definition.
     * Returns the effective field name after eventual sanitization.
     *
     * @param   string  $name               Name of the field. Invalid names will be sanitized
     *                                      (maximum 10 characters, only letters, numbers and underscores are allowed).
     *                                      Only letters, numbers and underscores are allowed.
     *
     * @return  string
     */
    public function addDateField($name)
    {
        return $this->addField($name, Shapefile::DBF_TYPE_DATE, 8, 0);
    }
    
    /**
     * Adds a logical/boolean field to the Shapefile definition.
     * Returns the effective field name after eventual sanitization.
     *
     * @param   string  $name               Name of the field. Invalid names will be sanitized
     *                                      (maximum 10 characters, only letters, numbers and underscores are allowed).
     *                                      Only letters, numbers and underscores are allowed.
     *
     * @return  string
     */
    public function addLogicalField($name)
    {
        return $this->addField($name, Shapefile::DBF_TYPE_LOGICAL, 1, 0);
    }
    
    /**
     * Adds a memo field to the Shapefile definition.
     * Returns the effective field name after eventual sanitization.
     *
     * @param   string  $name               Name of the field. Invalid names will be sanitized
     *                                      (maximum 10 characters, only letters, numbers and underscores are allowed).
     *                                      Only letters, numbers and underscores are allowed.
     *
     * @return  string
     */
    public function addMemoField($name)
    {
        return $this->addField($name, Shapefile::DBF_TYPE_MEMO, 10, 0);
    }
    
    /**
     * Adds numeric to the Shapefile definition.
     * Returns the effective field name after eventual sanitization.
     *
     * @param   string  $name               Name of the field. Invalid names will be sanitized
     *                                      (maximum 10 characters, only letters, numbers and underscores are allowed).
     *                                      Only letters, numbers and underscores are allowed.
     * @param   int     $size               Lenght of the field, between 1 and 254 characters. Defaults to 10.
     * @param   int     $decimals           Optional number of decimal digits. Defaults to 0.
     *
     * @return  string
     */
    public function addNumericField($name, $size = 10, $decimals = 0)
    {
        return $this->addField($name, Shapefile::DBF_TYPE_NUMERIC, $size, $decimals);
    }
    
    /**
     * Adds floating point to the Shapefile definition.
     * Returns the effective field name after eventual sanitization.
     *
     * @param   string  $name               Name of the field. Invalid names will be sanitized
     *                                      (maximum 10 characters, only letters, numbers and underscores are allowed).
     *                                      Only letters, numbers and underscores are allowed.
     * @param   int     $size               Lenght of the field, between 1 and 254 characters. Defaults to 20.
     * @param   int     $decimals           Number of decimal digits. Defaults to 10.
     *
     * @return  string
     */
    public function addFloatField($name, $size = 20, $decimals = 10)
    {
        return $this->addField($name, Shapefile::DBF_TYPE_FLOAT, $size, $decimals);
    }
    
    
    /**
     * Writes a record to the Shapefile.
     *
     * @param   \Shapefile\Geometry\Geometry    $Geometry   Geometry to write.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    public function writeRecord(Geometry\Geometry $Geometry)
    {
        // Init headers
        if (!$this->flag_init_headers) {
            $this->bufferData(Shapefile::FILE_SHP, $this->packNulPadding(Shapefile::SHP_HEADER_SIZE));
            $this->bufferData(Shapefile::FILE_SHX, $this->packNulPadding(Shapefile::SHX_HEADER_SIZE));
            $this->bufferData(Shapefile::FILE_DBF, $this->packNulPadding($this->getDBFHeaderSize()));
            if (in_array(Shapefile::DBF_TYPE_MEMO, $this->arrayColumn($this->getFields(), 'type'))) {
                if (!$this->isFileOpen(Shapefile::FILE_DBT)) {
                    throw new ShapefileException(Shapefile::ERR_FILE_MISSING, strtoupper(Shapefile::FILE_DBT));
                }
                $this->bufferData(Shapefile::FILE_DBT, $this->packNulPadding(Shapefile::DBT_BLOCK_SIZE));
                ++$this->dbt_next_available_block;
            }
            $this->flag_init_headers = true;
        }
        
        // Pair with Geometry
        $this->pairGeometry($Geometry);
        
        // Write data to temporary buffers to make sure no exceptions are raised within current record
        $temp = $this->packSHPAndSHXData($Geometry) + $this->packDBFAndDBTData($Geometry);
        // Write data to real buffers
        foreach (array_keys($this->buffers) as $file_type) {
            $this->bufferData($file_type, $temp[$file_type]);
        }
        $this->shp_current_offset       = $temp['shp_current_offset'];
        $this->dbt_next_available_block = $temp['dbt_next_available_block'];
        $this->setTotRecords($this->getTotRecords() + 1);
        ++$this->buffered_record_count;
        
        // Eventually flush buffers
        $option_buffered_records = $this->getOption(Shapefile::OPTION_BUFFERED_RECORDS);
        if ($option_buffered_records > 0 && $this->buffered_record_count == $option_buffered_records) {
            $this->writeBuffers();
        }
        
        return $this;
    }
    
    /**
     * Writes buffers to files.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    public function flushBuffer()
    {
        $this->writeBuffers();
        return $this;
    }
    
    
    
    /////////////////////////////// PRIVATE ///////////////////////////////
    /**
     * Truncates open files.
     */
    private function truncateFiles()
    {
        foreach (array_keys($this->getFiles()) as $file_type) {
            if ($this->getFileSize($file_type) > 0) {
                $this->fileTruncate($file_type);
                $this->setFilePointer($file_type, 0);
            }
        }
    }
    
    
    /**
     * Stores binary string packed data into a buffer.
     *
     * @param   string  $file_type      File type.
     * @param   string  $data           String value to write.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    private function bufferData($file_type, $data)
    {
        $this->buffers[$file_type] .= $data;
        return $this;
    }
    
    /**
     * Writes buffers to files.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    private function writeBuffers()
    {
        foreach ($this->buffers as $file_type => $buffer) {
            if ($buffer !== '') {
                $this->writeData($file_type, $buffer);
                $this->buffers[$file_type] = '';
            }
        }
        $this->buffered_record_count = 0;
        return $this;
    }
    
    
    /**
     * Packs an unsigned char into binary string.
     *
     * @param   string  $data       Value to pack.
     *
     * @return  string
     */
    private function packChar($data)
    {
        return pack('C', $data);
    }
    
    /**
     * Packs an unsigned short, 16 bit, little endian byte order, into binary string.
     *
     * @param   string  $data       Value to pack.
     *
     * @return  string
     */
    private function packInt16L($data)
    {
        return pack('v', $data);
    }
    
    /**
     * Packs an unsigned long, 32 bit, big endian byte order, into binary string.
     *
     * @param   string  $data       Value to pack.
     *
     * @return  string
     */
    private function packInt32B($data)
    {
        return pack('N', $data);
    }
    
    /**
     * Packs an unsigned long, 32 bit, little endian byte order, into binary string.
     *
     * @param   string  $data       Value to pack.
     *
     * @return  string
     */
    private function packInt32L($data)
    {
        return pack('V', $data);
    }
    
    /**
     * Packs a double, 64 bit, little endian byte order, into binary string.
     *
     * @param   string  $data       Value to pack.
     *
     * @return  string
     */
    private function packDoubleL($data)
    {
        $data = pack('d', $data);
        if ($this->isBigEndianMachine()) {
            $data = strrev($data);
        }
        return $data;
    }
    
    /**
     * Packs a string into binary string.
     *
     * @param   string  $data       Value to pack.
     *
     * @return  string
     */
    private function packString($data)
    {
        return pack('A*', $data);
    }
    
    /**
     * Packs a NUL-padding of given length into binary string.
     *
     * @param   string  $lenght     Length of the padding to pack.
     *
     * @return  string
     */
    private function packNulPadding($lenght)
    {
        return pack('a*', str_repeat("\0", $lenght));
    }
    
    
    /**
     * Packs some XY coordinates into binary string.
     *
     * @param   array   $coordinates    Array with "x" and "y" coordinates.
     *
     * @return  string
     */
    private function packXY($coordinates)
    {
        return $this->packDoubleL($coordinates['x'])
             . $this->packDoubleL($coordinates['y']);
    }
    
    /**
     * Packs a Z coordinate into binary string.
     *
     * @param   array   $coordinates    Array with "z" coordinate.
     *
     * @return  string
     */
    private function packZ($coordinates)
    {
        return $this->packDoubleL($this->getOption(Shapefile::OPTION_SUPPRESS_Z) ? 0 : $coordinates['z']);
    }
    
    /**
     * Packs an M coordinate into binary string.
     *
     * @param   array   $coordinates    Array with "m" coordinate.
     *
     * @return  string
     */
    private function packM($coordinates)
    {
        return $this->packDoubleL($this->getOption(Shapefile::OPTION_SUPPRESS_M) ? 0 : $this->parseM($coordinates['m']));
    }
    
    /**
     * Parses an M coordinate according to the ESRI specs:
     * «Any floating point number smaller than –10^38 is considered by a shapefile reader to represent a "no data" value»
     * This library uses bool value false to represent "no data".
     *
     * @param   float|bool  $value  Value to parse.
     *
     * @return  float
     */
    private function parseM($value)
    {
        return ($value === false) ? Shapefile::SHP_NO_DATA_VALUE : $value;
    }
    
    
    /**
     * Packs an XY bounding box into binary string.
     *
     * @param   array   $bounding_box   Associative array with xmin, xmax, ymin, ymax values.
     *
     * @return  string
     */
    private function packXYBoundingBox($bounding_box)
    {
        return $this->packDoubleL($bounding_box['xmin'])
             . $this->packDoubleL($bounding_box['ymin'])
             . $this->packDoubleL($bounding_box['xmax'])
             . $this->packDoubleL($bounding_box['ymax']);
    }
    
    /**
     * Packs a Z range into binary string.
     *
     * @param   array   $bounding_box   Associative array with zmin and zmax values.
     *
     * @return  string
     */
    private function packZRange($bounding_box)
    {
        return $this->packDoubleL($this->getOption(Shapefile::OPTION_SUPPRESS_Z) ? 0 : $bounding_box['zmin'])
             . $this->packDoubleL($this->getOption(Shapefile::OPTION_SUPPRESS_Z) ? 0 : $bounding_box['zmax']);
    }
    
    /**
     * Packs an M range into binary string.
     *
     * @param   array   $bounding_box   Associative array with mmin and mmax values.
     *
     * @return  string
     */
    private function packMRange($bounding_box)
    {
        return $this->packDoubleL($this->getOption(Shapefile::OPTION_SUPPRESS_M) ? 0 : $this->parseM($bounding_box['mmin']))
             . $this->packDoubleL($this->getOption(Shapefile::OPTION_SUPPRESS_M) ? 0 : $this->parseM($bounding_box['mmax']));
    }
    
    
    /**
     * Packs a Null shape into binary string.
     *
     * @return  string
     */
    private function packNull()
    {
        // Shape type
        return $this->packInt32L(Shapefile::SHAPE_TYPE_NULL);
    }
    
    /**
     * Packs a Point, PointM or PointZ shape into binary string.
     *
     * @param   \Shapefile\Geometry\Geometry    $Geometry   Geometry to pack.
     *
     * @return  string
     */
    private function packPoint(Geometry\Geometry $Geometry)
    {
        $array      = $Geometry->getArray();
        $is_m       = $this->isM();
        $is_z       = $this->isZ();
        $shape_type = $is_z ? Shapefile::SHAPE_TYPE_POINTZ : ($is_m ? Shapefile::SHAPE_TYPE_POINTM : Shapefile::SHAPE_TYPE_POINT);
        
        // Shape type
        $ret = $this->packInt32L($shape_type);
        // XY Coordinates
        $ret .= $this->packXY($array);
        
        if ($is_z) {
            // Z Coordinate
            $ret .= $this->packZ($array);
        }
        
        if ($is_m) {
            // M Coordinate
            $ret .= $this->packM($array);
        }
        
        return $ret;
    }
    
    /**
     * Packs a MultiPoint, MultiPointM or MultiPointZ shape into binary string.
     *
     * @param   \Shapefile\Geometry\Geometry    $Geometry   Geometry to pack.
     *
     * @return  string
     */
    private function packMultiPoint(Geometry\Geometry $Geometry)
    {
        $array          = $Geometry->getArray();
        $bounding_box   = $Geometry->getBoundingBox();
        $is_m           = $this->isM();
        $is_z           = $this->isZ();
        $shape_type     = $is_z ? Shapefile::SHAPE_TYPE_MULTIPOINTZ : ($is_m ? Shapefile::SHAPE_TYPE_MULTIPOINTM : Shapefile::SHAPE_TYPE_MULTIPOINT);
        
        // Shape type
        $ret = $this->packInt32L($shape_type);
        // XY Bounding Box
        $ret .= $this->packXYBoundingBox($bounding_box);
        // NumPoints
        $ret .= $this->packInt32L($array['numpoints']);
        // Points
        foreach ($array['points'] as $coordinates) {
            $ret .= $this->packXY($coordinates);
        }
        
        if ($is_z) {
            // Z Range
            $ret .= $this->packZRange($bounding_box);
            // Z Array
            foreach ($array['points'] as $coordinates) {
                $ret .= $this->packZ($coordinates);
            }
        }
        
        if ($is_m) {
            // M Range
            $ret .= $this->packMRange($bounding_box);
            // M Array
            foreach ($array['points'] as $coordinates) {
                $ret .= $this->packM($coordinates);
            }
        }
        
        return $ret;
    }
    
    /**
     * Packs a PolyLine, PolyLineM or PolyLineZ shape into binary string.
     *
     * @param   \Shapefile\Geometry\Geometry    $Geometry       Geometry to pack.
     * @param   bool                            $flag_polygon   Optional flag to pack Polygon shapes.
     *
     * @return  string
     */
    private function packPolyLine(Geometry\Geometry $Geometry, $flag_polygon = false)
    {
        $array          = $Geometry->getArray();
        $bounding_box   = $Geometry->getBoundingBox();
        $is_m           = $this->isM();
        $is_z           = $this->isZ();
        if ($flag_polygon) {
            $shape_type = $is_z ? Shapefile::SHAPE_TYPE_POLYGONZ : ($is_m ? Shapefile::SHAPE_TYPE_POLYGONM : Shapefile::SHAPE_TYPE_POLYGON);
        } else {
            $shape_type = $is_z ? Shapefile::SHAPE_TYPE_POLYLINEZ : ($is_m ? Shapefile::SHAPE_TYPE_POLYLINEM : Shapefile::SHAPE_TYPE_POLYLINE);
        }
        
        // PolyLines and Polygons are always MultiLinestrings and MultiPolygons in Shapefiles
        if (!isset($array['parts'])) {
            $array = [
                'numparts'  => 1,
                'parts'     => [$array],
            ];
        }
        
        // Polygons need to be reduced as PolyLines
        if ($flag_polygon) {
            $parts = [];
            foreach ($array['parts'] as $part) {
                foreach ($part['rings'] as $ring) {
                    $parts[] = $ring;
                }
            }
            $array = [
                'numparts'  => count($parts),
                'parts'     => $parts,
            ];
        }
        
        // Shape type
        $ret = $this->packInt32L($shape_type);
        // XY Bounding Box
        $ret .= $this->packXYBoundingBox($bounding_box);
        // NumParts
        $ret .= $this->packInt32L($array['numparts']);
        // NumPoints
        $ret .= $this->packInt32L(array_sum($this->arrayColumn($array['parts'], 'numpoints')));
        // Parts
        $part_first_index = 0;
        foreach ($array['parts'] as $part) {
            $ret .= $this->packInt32L($part_first_index);
            $part_first_index += $part['numpoints'];
        }
        // Points
        foreach ($array['parts'] as $part) {
            foreach ($part['points'] as $coordinates) {
                $ret .= $this->packXY($coordinates);
            }
        }
        
        if ($is_z) {
            // Z Range
            $ret .= $this->packZRange($bounding_box);
            // Z Array
            foreach ($array['parts'] as $part) {
                foreach ($part['points'] as $coordinates) {
                    $ret .= $this->packZ($coordinates);
                }
            }
        }
        
        if ($is_m) {
            // M Range
            $ret .= $this->packMRange($bounding_box);
            // M Array
            foreach ($array['parts'] as $part) {
                foreach ($part['points'] as $coordinates) {
                    $ret .= $this->packM($coordinates);
                }
            }
        }
        
        return $ret;
    }
    
    /**
     * Packs a Polygon, PolygonM or PolygonZ shape into binary string.
     * It forces closed rings and clockwise orientation in order to comply with ESRI Shapefile specifications.
     *
     * @param   \Shapefile\Geometry\Geometry    $Geometry   Geometry to pack.
     *
     * @return  string
     */
    private function packPolygon(Geometry\Geometry $Geometry)
    {
        $Geometry->forceClosedRings();
        $Geometry->forceClockwise();
        return $this->packPolyLine($Geometry, true);
    }
    
    
    /**
     * Packs SHP and SHX data from a Geometry object into binary strings and returns an array with SHP, SHX and "shp_current_offset" members.
     *
     * @param   \Shapefile\Geometry\Geometry    $Geometry   Input Geometry.
     *
     * @return  array
     */
    private function packSHPAndSHXData(Geometry\Geometry $Geometry)
    {
        // Pack Geometry data
        $method = self::$shp_pack_methods[$Geometry->isEmpty() ? Shapefile::SHAPE_TYPE_NULL : $this->getBasetype()];
        $shp_data = $this->{$method}($Geometry);
        
        // Compute content lenght in 16-bit words
        $shp_content_length = strlen($shp_data) / 2;
        
        return [
            Shapefile::FILE_SHP     => $this->packInt32B($this->getTotRecords() + 1)
                                     . $this->packInt32B($shp_content_length)
                                     . $shp_data,
            Shapefile::FILE_SHX     => $this->packInt32B($this->shp_current_offset)
                                     . $this->packInt32B($shp_content_length),
            'shp_current_offset'    => $this->shp_current_offset + $shp_content_length + 4,
        ];
    }
    
    /**
     * Packs DBF and DBT data from a Geometry object into binary strings and returns an array with DBF, DBT and "dbt_next_available_block" members.
     *
     * @param   \Shapefile\Geometry\Geometry    $Geometry   Input Geometry.
     *
     * @return  array
     */
    private function packDBFAndDBTData(Geometry\Geometry $Geometry)
    {
        $ret = [
            Shapefile::FILE_DBF         => '',
            Shapefile::FILE_DBT         => '',
            'dbt_next_available_block'  => $this->dbt_next_available_block,
        ];
        
        // Deleted flag
        $ret[Shapefile::FILE_DBF] = $this->packChar($Geometry->isDeleted() ? Shapefile::DBF_DELETED_MARKER : Shapefile::DBF_BLANK);
        
        // Data
        $data = $Geometry->getDataArray();
        if ($this->getOption(Shapefile::OPTION_DBF_FORCE_ALL_CAPS)) {
            $data = array_change_key_case($data, CASE_UPPER);
        }
        foreach ($this->getFields() as $name => $field) {
            if (!array_key_exists($name, $data)) {
                if ($this->getOption(Shapefile::OPTION_ENFORCE_GEOMETRY_DATA_STRUCTURE)) {
                    throw new ShapefileException(Shapefile::ERR_GEOM_MISSING_FIELD, $name);
                }
                $data[$name] = null;
            }
            $value = $this->encodeFieldValue($field['type'], $field['size'], $field['decimals'], $data[$name]);
            // Memo (DBT)
            if ($field['type'] == Shapefile::DBF_TYPE_MEMO && $value !== null) {
                $dbt    = $this->packDBTData($value, $field['size']);
                $value  = str_pad($ret['dbt_next_available_block'], $field['size'], chr(Shapefile::DBF_BLANK), STR_PAD_LEFT);
                $ret[Shapefile::FILE_DBT]           .= $dbt['data'];
                $ret['dbt_next_available_block']    += $dbt['blocks'];
            }
            // Null
            if ($value === null) {
                $value = str_repeat(($this->getOption(Shapefile::OPTION_DBF_NULL_PADDING_CHAR) !== null ? $this->getOption(Shapefile::OPTION_DBF_NULL_PADDING_CHAR) : chr(Shapefile::DBF_BLANK)), $field['size']);
            }
            // Add packed value to temp buffer
            $ret[Shapefile::FILE_DBF] .= $this->packString($value);
        }
        
        return $ret;
    }
    
    /**
     * Packs DBT data into a binary string and return an array with "blocks" and "data" members.
     *
     * @param   string  $data           Data to write
     * @param   int     $field_size     Size of the DBF field.
     *
     * @return  array
     */
    private function packDBTData($data, $field_size)
    {
        $ret = [
            'blocks'    => 0,
            'data'      => '',
        ];
        
        // Ignore empty values
        if ($data === '') {
            $ret['data'] = str_repeat(chr(Shapefile::DBF_BLANK), $field_size);
        } else {
            // Corner case: there's not enough space at the end of the last block for 2 field terminators. Add a space and switch to the next block!
            if (strlen($data) % Shapefile::DBT_BLOCK_SIZE == Shapefile::DBT_BLOCK_SIZE - 1) {
                $data .= chr(Shapefile::DBF_BLANK);
            }
            // Add TWO field terminators
            $data .= str_repeat(chr(Shapefile::DBT_FIELD_TERMINATOR), 2);
            // Write data to DBT buffer
            foreach (str_split($data, Shapefile::DBT_BLOCK_SIZE) as $block) {
                $ret['blocks']  += 1;
                $ret['data']    .= $this->packString(str_pad($block, Shapefile::DBT_BLOCK_SIZE, "\0", STR_PAD_RIGHT));
            }
        }
        
        return $ret;
    }
    
    /**
     * Encodes a value to be written into a DBF field as a raw string.
     *
     * @param   string  $type       Type of the field.
     * @param   int     $size       Lenght of the field.
     * @param   int     $decimals   Number of decimal digits for numeric type.
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
                if (is_string($value)) {
                    $value = substr(trim($value), 0, 1);
                    if (strpos(Shapefile::DBF_VALUE_MASK_TRUE, $value) !== false) {
                        $value = Shapefile::DBF_VALUE_TRUE;
                    } elseif (strpos(Shapefile::DBF_VALUE_MASK_FALSE, $value) !== false) {
                        $value = Shapefile::DBF_VALUE_FALSE;
                    } else {
                        $value = Shapefile::DBF_VALUE_NULL;
                    }
                } elseif (is_bool($value) || is_int($value) || is_float($value)) {
                    $value = $value ? Shapefile::DBF_VALUE_TRUE : Shapefile::DBF_VALUE_FALSE;
                } else {
                    $value = Shapefile::DBF_VALUE_NULL;
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
                    if (is_string($value)) {
                        $value          = trim($value);
                        $flag_negative  = substr($value, 0, 1) === '-';
                        $intpart        = $this->sanitizeNumber(strpos($value, '.') === false ? $value : strstr($value, '.', true));
                        $decpart        = $this->sanitizeNumber(substr(strstr($value, '.', false), 1));
                        $decpart        = strlen($decpart) > $decimals ? substr($decpart, 0, $decimals) : str_pad($decpart, $decimals, '0', STR_PAD_RIGHT);
                        $value          = ($flag_negative ? '-' : '') . $intpart . ($decimals > 0 ? '.' : '') . $decpart;
                    } else {
                        $value = number_format(floatval($value), $decimals, '.', '');
                    }
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
     * @param   int     $size       Lenght of the field.
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
     * Packs SHP or SHX file header into a binary string.
     *
     * @param   int     $file_size      File size in bytes.
     *
     * @return  string
     */
    private function packSHPOrSHXHeader($file_size)
    {
        $ret = '';
        
        // File Code
        $ret .= $this->packInt32B(Shapefile::SHP_FILE_CODE);
        
        // Unused bytes
        $ret .= $this->packNulPadding(20);
        
        // File Length (in 16-bit words)
        $ret .= $this->packInt32B($file_size / 2);
        
        // Version
        $ret .= $this->packInt32L(Shapefile::SHP_VERSION);
        
        // Shape Type
        $ret .= $this->packInt32L($this->getShapeType(Shapefile::FORMAT_INT));
        
        //Bounding Box (Defaults to all zeros for empty Shapefile)
        $bounding_box = $this->getBoundingBox() ?: [
            'xmin' => -Shapefile::SHP_NO_DATA_VALUE,
            'ymin' => -Shapefile::SHP_NO_DATA_VALUE,
            'xmax' => Shapefile::SHP_NO_DATA_VALUE,
            'ymax' => Shapefile::SHP_NO_DATA_VALUE,
            'zmin' => -Shapefile::SHP_NO_DATA_VALUE,
            'zmax' => Shapefile::SHP_NO_DATA_VALUE,
            'mmin' => -Shapefile::SHP_NO_DATA_VALUE,
            'mmax' => Shapefile::SHP_NO_DATA_VALUE,
        ];
        $ret .= $this->packXYBoundingBox($bounding_box);
        $ret .= $this->packZRange($this->isZ() ? $bounding_box : ['zmin' => 0, 'zmax' => 0]);
        $ret .= $this->packMRange($this->isM() ? $bounding_box : ['mmin' => 0, 'mmax' => 0]);
        
        return $ret;
    }
    
    /**
     * Packs DBF file header into a binary string.
     *
     * @return  string
     */
    private function packDBFHeader()
    {
        $ret = '';
        
        // Version number
        $ret .= $this->packChar($this->dbt_next_available_block > 0 ? Shapefile::DBF_VERSION_WITH_DBT : Shapefile::DBF_VERSION);
        
        // Date of last update
        $ret .= $this->packChar(intval(date('Y')) - 1900);
        $ret .= $this->packChar(intval(date('m')));
        $ret .= $this->packChar(intval(date('d')));
        
        // Number of records
        $ret .= $this->packInt32L($this->getTotRecords());

        // Header size
        $ret .= $this->packInt16L($this->getDBFHeaderSize());
        
         // Record size
        $ret .= $this->packInt16L($this->getDBFRecordSize());
        
        // Reserved bytes
        $ret .= $this->packNulPadding(20);
        
        // Field descriptor array
        foreach ($this->getFields() as $name => $field) {
            // Name
            $ret .= $this->packString(str_pad($name, 10, "\0", STR_PAD_RIGHT));
            $ret .= $this->packNulPadding(1);
            // Type
            $ret .= $this->packString($field['type']);
            $ret .= $this->packNulPadding(4);
            // Size
            $ret .= $this->packChar($field['size']);
            // Decimals
            $ret .= $this->packChar($field['decimals']);
            $ret .= $this->packNulPadding(14);
        }
        
        // Field terminator
        $ret .= $this->packChar(Shapefile::DBF_FIELD_TERMINATOR);
        
        return $ret;
    }
    
    /**
     * Packs DBT file header into a binary string.
     *
     * @return  string
     */
    private function packDBTHeader()
    {
        $ret = '';
        
        // Next available block
        $ret .= $this->packInt32L($this->dbt_next_available_block);
        
        // Reserved bytes
        $ret .= $this->packNulPadding(12);
        
        // Version number
        $ret .= $this->packChar(Shapefile::DBF_VERSION);
        
        return $ret;
    }
    
    /**
     * Computes DBF header size.
     * 32bytes + (number of fields x 32) + 1 (field terminator character).
     *
     * @return  int
     */
    private function getDBFHeaderSize()
    {
        return 33 + (32 * count($this->getFields()));
    }
    
    /**
     * Computes DBF record size.
     * Sum of all fields sizes + 1 (record deleted flag).
     *
     * @return  int
     */
    private function getDBFRecordSize()
    {
        return array_sum($this->arrayColumn($this->getFields(), 'size')) + 1;
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
        return array_map(function ($element) use ($key) {
            return $element[$key];
        }, $array);
    }
}
