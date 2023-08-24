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

use Shapefile\Geometry\Point;
use Shapefile\Geometry\MultiPoint;
use Shapefile\Geometry\Linestring;
use Shapefile\Geometry\MultiLinestring;
use Shapefile\Geometry\Polygon;
use Shapefile\Geometry\MultiPolygon;

/**
 * ShapefileReader class.
 */
class ShapefileReader extends Shapefile implements \Iterator
{
    /** SHP read methods hash */
    private static $shp_read_methods = [
        Shapefile::SHAPE_TYPE_NULL          => 'readNull',
        Shapefile::SHAPE_TYPE_POINT         => 'readPoint',
        Shapefile::SHAPE_TYPE_POLYLINE      => 'readPolyLine',
        Shapefile::SHAPE_TYPE_POLYGON       => 'readPolygon',
        Shapefile::SHAPE_TYPE_MULTIPOINT    => 'readMultiPoint',
        Shapefile::SHAPE_TYPE_POINTZ        => 'readPointZ',
        Shapefile::SHAPE_TYPE_POLYLINEZ     => 'readPolyLineZ',
        Shapefile::SHAPE_TYPE_POLYGONZ      => 'readPolygonZ',
        Shapefile::SHAPE_TYPE_MULTIPOINTZ   => 'readMultiPointZ',
        Shapefile::SHAPE_TYPE_POINTM        => 'readPointM',
        Shapefile::SHAPE_TYPE_POLYLINEM     => 'readPolyLineM',
        Shapefile::SHAPE_TYPE_POLYGONM      => 'readPolygonM',
        Shapefile::SHAPE_TYPE_MULTIPOINTM   => 'readMultiPointM',
    ];
    
    
    /**
     * @var array   DBF field names map: fields are numerically indexed into DBF files.
     */
    private $dbf_fields = [];
    
    /**
     * @var int     DBF file size in bytes.
     */
    private $dbf_file_size;
    
    /**
     * @var int     DBF file header size in bytes.
     */
    private $dbf_header_size;
    
    /**
     * @var int     DBF file record size in bytes.
     */
    private $dbf_record_size;
    
    /**
     * @var int     DBT file size in bytes.
     */
    private $dbt_file_size;
    
    /**
     * @var int     SHP file size in bytes (used when SHX file is not available).
     */
    private $shp_file_size;
    
    /**
     * @var int     Pointer to current SHP and DBF files record.
     */
    private $current_record;
    
    
    
    /////////////////////////////// PUBLIC ///////////////////////////////
    /**
     * Constructor.
     *
     * @param   string|array    $files      Path to SHP file / Array of paths / Array of handles of individual files.
     * @param   array           $options    Optional associative array of options.
     */
    public function __construct($files, $options = [])
    {
        // Deprecated options
        if (isset($options[Shapefile::OPTION_ENFORCE_POLYGON_CLOSED_RINGS])) {
            $options = array_merge([
                Shapefile::OPTION_POLYGON_CLOSED_RINGS_ACTION => $options[Shapefile::OPTION_ENFORCE_POLYGON_CLOSED_RINGS] ? Shapefile::ACTION_CHECK : Shapefile::ACTION_IGNORE,
            ], $options);
        }
        if (isset($options[Shapefile::OPTION_INVERT_POLYGONS_ORIENTATION])) {
            $options = array_merge([
                Shapefile::OPTION_POLYGON_OUTPUT_ORIENTATION => $options[Shapefile::OPTION_INVERT_POLYGONS_ORIENTATION] ? Shapefile::ORIENTATION_COUNTERCLOCKWISE : Shapefile::ORIENTATION_CLOCKWISE,
            ], $options);
        }
        
        // Options
        $this->initOptions([
            Shapefile::OPTION_DBF_ALLOW_FIELD_SIZE_255,
            Shapefile::OPTION_DBF_CONVERT_TO_UTF8,
            Shapefile::OPTION_DBF_FORCE_ALL_CAPS,
            Shapefile::OPTION_DBF_IGNORED_FIELDS,
            Shapefile::OPTION_DBF_NULL_PADDING_CHAR,
            Shapefile::OPTION_DBF_NULLIFY_INVALID_DATES,
            Shapefile::OPTION_DBF_RETURN_DATES_AS_OBJECTS,
            Shapefile::OPTION_FORCE_MULTIPART_GEOMETRIES,
            Shapefile::OPTION_IGNORE_FILE_DBF,
            Shapefile::OPTION_IGNORE_FILE_SHX,
            Shapefile::OPTION_IGNORE_GEOMETRIES_BBOXES,
            Shapefile::OPTION_IGNORE_SHAPEFILE_BBOX,
            Shapefile::OPTION_POLYGON_CLOSED_RINGS_ACTION,
            Shapefile::OPTION_POLYGON_ORIENTATION_READING_AUTOSENSE,
            Shapefile::OPTION_POLYGON_OUTPUT_ORIENTATION,
            Shapefile::OPTION_SUPPRESS_M,
            Shapefile::OPTION_SUPPRESS_Z,
        ], $options);
        
        // Open files
        $this->openFiles($files, false, [
            Shapefile::FILE_DBF => $this->getOption(Shapefile::OPTION_IGNORE_FILE_DBF),
            Shapefile::FILE_SHX => $this->getOption(Shapefile::OPTION_IGNORE_FILE_SHX),
        ]);
        
        // SHX
        $this->setTotRecords($this->getOption(Shapefile::OPTION_IGNORE_FILE_SHX) ? Shapefile::UNKNOWN : ($this->getFileSize(Shapefile::FILE_SHX) - Shapefile::SHX_HEADER_SIZE) / Shapefile::SHX_RECORD_SIZE);
        
        // SHP
        $this->shp_file_size = $this->getFileSize(Shapefile::FILE_SHP);
        $this->readSHPHeader();
        
        // DBF and DBT
        if (!$this->getOption(Shapefile::OPTION_IGNORE_FILE_DBF)) {
            // DBF file size
            $this->dbf_file_size = $this->getFileSize(Shapefile::FILE_DBF);
            // DBT file size
            $this->dbt_file_size = ($this->isFileOpen(Shapefile::FILE_DBT) && $this->getFileSize(Shapefile::FILE_DBT) > 0) ? $this->getFileSize(Shapefile::FILE_DBT) : null;
            // Read DBF header
            $this->readDBFHeader();
        }
        // PRJ
        if ($this->isFileOpen(Shapefile::FILE_PRJ) && $this->getFileSize(Shapefile::FILE_PRJ) > 0) {
            $this->setPRJ($this->readString(Shapefile::FILE_PRJ, $this->getFileSize(Shapefile::FILE_PRJ)));
        }
        
        // CPG
        if ($this->isFileOpen(Shapefile::FILE_CPG) && $this->getFileSize(Shapefile::FILE_CPG) > 0) {
            $this->setCharset($this->readString(Shapefile::FILE_CPG, $this->getFileSize(Shapefile::FILE_CPG)));
        }
        
        // Init record pointer
        $this->rewind();
    }
    
    /**
     * Destructor.
     *
     * Closes all files.
     */
    public function __destruct()
    {
        $this->closeFiles();
    }
    
    
    public function rewind()
    {
        $this->current_record = 0;
        $this->next();
    }
    
    public function next()
    {
        ++$this->current_record;
        if (($this->getTotRecords() !== Shapefile::UNKNOWN && !$this->checkRecordIndex($this->current_record)) || $this->getFilePointer(Shapefile::FILE_SHP) >= $this->shp_file_size) {
            $this->current_record = Shapefile::EOF;
        }
    }
    
    public function current()
    {
        return $this->readCurrentRecord();
    }

    public function key()
    {
        return $this->current_record;
    }

    public function valid()
    {
        return ($this->current_record !== Shapefile::EOF);
    }
    
    
    /**
     * Gets current record index.
     *
     * Note that records count starts from 1 in Shapefiles.
     * When the last record is reached, the special value Shapefile::EOF will be returned.
     *
     * @return  int
     */
    public function getCurrentRecord()
    {
        return $this->current_record;
    }
    
    /**
     * Sets current record index. Throws an exception if provided index is out of range.
     *
     * @param   int     $index   Index of the record to select.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    public function setCurrentRecord($index)
    {
        if ($this->getTotRecords() === Shapefile::UNKNOWN) {
            throw new ShapefileException(Shapefile::ERR_INPUT_RANDOM_ACCESS_UNAVAILABLE);
        }
        if (!$this->checkRecordIndex($index)) {
            throw new ShapefileException(Shapefile::ERR_INPUT_RECORD_NOT_FOUND, $index);
        }
        $this->current_record = $index;
        return $this;
    }
    
    /**
     * Gets current record and moves the cursor to the next one.
     *
     * @return  \Shapefile\Geometry\Geometry
     */
    public function fetchRecord()
    {
        $ret = $this->readCurrentRecord();
        if ($ret !== false) {
            $this->next();
        }
        return $ret;
    }
    
    
    
    /////////////////////////////// PRIVATE ///////////////////////////////
    /**
     * Reads an unsigned char from a resource handle.
     *
     * @param   string  $file_type      File type.
     *
     * @return  int
     */
    private function readChar($file_type)
    {
        return current(unpack('C', $this->readData($file_type, 1)));
    }
    
    /**
     * Reads an unsigned short, 16 bit, little endian byte order, from a resource handle.
     *
     * @param   string  $file_type      File type.
     *
     * @return  int
     */
    private function readInt16L($file_type)
    {
        return current(unpack('v', $this->readData($file_type, 2)));
    }
    
    /**
     * Reads an unsigned long, 32 bit, big endian byte order, from a resource handle.
     *
     * @param   string  $file_type      File type.
     *
     * @return  int
     */
    private function readInt32B($file_type)
    {
        return current(unpack('N', $this->readData($file_type, 4)));
    }
    
    /**
     * Reads an unsigned long, 32 bit, little endian byte order, from a resource handle.
     *
     * @param   string  $file_type      File type.
     *
     * @return  int
     */
    private function readInt32L($file_type)
    {
        return current(unpack('V', $this->readData($file_type, 4)));
    }
    
    /**
     * Reads a double, 64 bit, little endian byte order, from a resource handle.
     *
     * @param   string  $file_type      File type.
     *
     * @return  double
     */
    private function readDoubleL($file_type)
    {
        $ret = $this->readData($file_type, 8);
        if ($this->isBigEndianMachine()) {
            $ret = strrev($ret);
        }
        return current(unpack('d', $ret));
    }
    
    /**
     * Reads a string of given length from a resource handle and optionally converts it to UTF-8.
     *
     * @param   string  $file_type          File type.
     * @param   int     $length             Length of the string to read.
     * @param   bool    $flag_utf8_encode   Optional flag to convert output to UTF-8 if OPTION_DBF_CONVERT_TO_UTF8 is enabled.
     *
     * @return  string
     */
    private function readString($file_type, $length, $flag_utf8_encode = false)
    {
        $ret = current(unpack('A*', $this->readData($file_type, $length)));
        if ($flag_utf8_encode && $this->getOption(Shapefile::OPTION_DBF_CONVERT_TO_UTF8)) {
            $ret = @iconv($this->getCharset(), 'UTF-8', $ret);
            if ($ret === false) {
                throw new ShapefileException(Shapefile::ERR_DBF_CHARSET_CONVERSION);
            }
        }
        return trim($ret);
    }
    
    
    /**
     * Checks whether a record index value is valid or not.
     *
     * @param   int     $index      The index value to check.
     *
     * @return  bool
     */
    private function checkRecordIndex($index)
    {
        return ($index > 0 && $index <= $this->getTotRecords());
    }
    
    
    /**
     * Reads SHP file header.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    private function readSHPHeader()
    {
        // Shape Type
        $this->setFilePointer(Shapefile::FILE_SHP, 32);
        $this->setShapeType($this->readInt32L(Shapefile::FILE_SHP));
        
        // Read Bounding Box if there are any records in the file.
        // Z and M ranges are always present in the Shapefile, although with value 0 if not used.
        if (!$this->getOption(Shapefile::OPTION_IGNORE_SHAPEFILE_BBOX) && ($this->getTotRecords() === Shapefile::UNKNOWN || $this->getTotRecords() > 0)) {
            $bounding_box = $this->readXYBoundingBox() + $this->readZRange() + $this->readMRange();
            if (!$this->isZ()) {
                unset($bounding_box['zmin']);
                unset($bounding_box['zmax']);
            }
            if (!$this->isM()) {
                unset($bounding_box['mmin']);
                unset($bounding_box['mmax']);
            }
            $this->setCustomBoundingBox($bounding_box);
        }
        
        // Set SHP file pointer to first record
        $this->setFilePointer(Shapefile::FILE_SHP, Shapefile::SHP_HEADER_SIZE);
        return $this;
    }
    
    /**
     * Reads DBF file header.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    private function readDBFHeader()
    {
        // Number of records
        $this->setFilePointer(Shapefile::FILE_DBF, 4);
        if ($this->readInt32L(Shapefile::FILE_DBF) !== $this->getTotRecords() && $this->getTotRecords() !== Shapefile::UNKNOWN) {
            throw new ShapefileException(Shapefile::ERR_DBF_MISMATCHED_FILE);
        }
        
        // Header and Record size
        $this->dbf_header_size = $this->readInt16L(Shapefile::FILE_DBF);
        $this->dbf_record_size = $this->readInt16L(Shapefile::FILE_DBF);
        
        // Fields
        $this->dbf_fields = [];
        $this->setFilePointer(Shapefile::FILE_DBF, 32);
        while ($this->getFilePointer(Shapefile::FILE_DBF) < $this->dbf_header_size - 1) {
            $name       = $this->normalizeDBFFieldNameCase($this->readString(Shapefile::FILE_DBF, 10));
            $this->setFileOffset(Shapefile::FILE_DBF, 1);
            $type       = $this->readString(Shapefile::FILE_DBF, 1);
            $this->setFileOffset(Shapefile::FILE_DBF, 4);
            $size       = $this->readChar(Shapefile::FILE_DBF);
            $decimals   = $this->readChar(Shapefile::FILE_DBF);
            $ignored    = in_array($name, $this->getOption(Shapefile::OPTION_DBF_IGNORED_FIELDS));
            if ($type === Shapefile::DBF_TYPE_MEMO && !$ignored && !$this->isFileOpen(Shapefile::FILE_DBT)) {
                throw new ShapefileException(Shapefile::ERR_FILE_MISSING, strtoupper(Shapefile::FILE_DBT));
            }
            $this->dbf_fields[] = [
                'name'      => $ignored ? null : $this->addField($name, $type, $size, $decimals, true),
                'ignored'   => $ignored,
                'size'      => $size,
            ];
            $this->setFileOffset(Shapefile::FILE_DBF, 14);
        }
        
        // Field terminator byte
        if ($this->readChar(Shapefile::FILE_DBF) !== Shapefile::DBF_FIELD_TERMINATOR) {
            throw new ShapefileException(Shapefile::ERR_DBF_FILE_NOT_VALID);
        }
        
        return $this;
    }
    
    
    /**
     * Reads current record in both SHP and DBF files and returns a Geometry.
     *
     * @return  \Shapefile\Geometry\Geometry
     */
    private function readCurrentRecord()
    {
        if (!$this->valid()) {
            return false;
        }
        
        // === SHX ===
        if (!$this->getOption(Shapefile::OPTION_IGNORE_FILE_SHX)) {
            $this->setFilePointer(Shapefile::FILE_SHX, Shapefile::SHX_HEADER_SIZE + (($this->current_record - 1) * Shapefile::SHX_RECORD_SIZE));
            // Offset (stored as 16-bit words)
            $shp_offset = $this->readInt32B(Shapefile::FILE_SHX) * 2;
        } else {
            $shp_offset = $this->getFilePointer(Shapefile::FILE_SHP);
        }
        
        // === SHP ===
        $this->setFilePointer(Shapefile::FILE_SHP, $shp_offset);
        // Skip Record Number
        $this->setFileOffset(Shapefile::FILE_SHP, 4);
        // Content length (stored as 16-bit words and missing the record header size)
        $content_length = ($this->readInt32B(Shapefile::FILE_SHP) * 2) + Shapefile::SHP_REC_HEADER_SIZE;
        // Shape type
        $shape_type = $this->readInt32L(Shapefile::FILE_SHP);
        if ($shape_type != Shapefile::SHAPE_TYPE_NULL && $shape_type != $this->getShapeType()) {
            throw new ShapefileException(Shapefile::ERR_SHP_WRONG_RECORD_TYPE, $shape_type);
        }
        // Read Geometry
        $Geometry = $this->{self::$shp_read_methods[$shape_type]}();
        // Set SHP file pointer position to next record if there is no SHX file available
        if ($this->getOption(Shapefile::OPTION_IGNORE_FILE_SHX)) {
            $this->setFilePointer(Shapefile::FILE_SHP, $shp_offset + $content_length);
        }
        
        // === DBF ===
        if (!$this->getOption(Shapefile::OPTION_IGNORE_FILE_DBF)) {
            $dbf_file_position = $this->dbf_header_size + (($this->current_record - 1) * $this->dbf_record_size);
            // Check if DBF is not corrupted (some "naive" users try to edit the DBF separately...)
            // Some GIS do not include the last Shapefile::DBF_EOF_MARKER (0x1a) byte in the DBF file, hence the "- 1" in the following line
            if ($dbf_file_position - 1 >= $this->dbf_file_size - $this->dbf_record_size) {
                throw new ShapefileException(Shapefile::ERR_DBF_EOF_REACHED);
            }
            $this->setFilePointer(Shapefile::FILE_DBF, $dbf_file_position);
            $Geometry->setFlagDeleted($this->readChar(Shapefile::FILE_DBF) === Shapefile::DBF_DELETED_MARKER);
            foreach ($this->dbf_fields as $i => $f) {
                if ($f['ignored']) {
                    $this->setFileOffset(Shapefile::FILE_DBF, $f['size']);
                } else {
                    $type   = $this->getField($f['name'])['type'];
                    $value  = $this->decodeFieldValue($f['name'], $type, $this->readString(Shapefile::FILE_DBF, $f['size'], true));
                    // Memo (DBT)
                    if ($type === Shapefile::DBF_TYPE_MEMO && $value) {
                        $this->setFilePointer(Shapefile::FILE_DBT, intval($value) * Shapefile::DBT_BLOCK_SIZE);
                        $value = '';
                        do {
                            if ($this->getFilePointer(Shapefile::FILE_DBT) >= $this->dbt_file_size) {
                                throw new ShapefileException(Shapefile::ERR_DBT_EOF_REACHED);
                            }
                            $value .= $this->readString(Shapefile::FILE_DBT, Shapefile::DBT_BLOCK_SIZE, true);
                        // Some software only sets ONE field terminator instead of TWO, hence the weird loop condition check:
                        } while (ord(substr($value, -1)) != Shapefile::DBT_FIELD_TERMINATOR && ord(substr($value, -2, 1)) != Shapefile::DBT_FIELD_TERMINATOR);
                        $value = substr($value, 0, -2);
                    }
                    $Geometry->setData($f['name'], $value);
                }
            }
        }
        
        // Pair Geometry with Shapefile and return it
        $this->pairGeometry($Geometry);
        return $Geometry;
    }
    
    
    /**
     * Decodes a raw value read from a DBF field.
     *
     * @param   string  $field      Name of the field.
     * @param   string  $type       Type of the field.
     * @param   string  $value      Raw value to decode.
     *
     * @return  mixed
     */
    private function decodeFieldValue($field, $type, $value)
    {
        if ($this->getOption(Shapefile::OPTION_DBF_NULL_PADDING_CHAR) !== null && $value == str_repeat($this->getOption(Shapefile::OPTION_DBF_NULL_PADDING_CHAR), $this->getField($field)['size'])) {
            $value = null;
        } else {
            switch ($type) {
                case Shapefile::DBF_TYPE_DATE:
                    $DateTime   = \DateTime::createFromFormat('Ymd', $value);
                    $errors     = \DateTime::getLastErrors();
                    if ($errors['warning_count'] || $errors['error_count']) {
                        $value = $this->getOption(Shapefile::OPTION_DBF_NULLIFY_INVALID_DATES) ? null : $value;
                    } elseif ($this->getOption(Shapefile::OPTION_DBF_RETURN_DATES_AS_OBJECTS)) {
                        $DateTime->setTime(0, 0, 0);
                        $value = $DateTime;
                    } else {
                        $value = $DateTime->format('Y-m-d');
                    }
                    break;
                    
                case Shapefile::DBF_TYPE_LOGICAL:
                    if (strpos(Shapefile::DBF_VALUE_MASK_TRUE, $value) !== false) {
                        $value = true;
                    } elseif (strpos(Shapefile::DBF_VALUE_MASK_FALSE, $value) !== false) {
                        $value = false;
                    } else {
                        $value = null;
                    }
                    break;
            }
        }
        return $value;
    }
    
    
    /**
     * Reads an XY pair of coordinates and returns an associative array.
     *
     * @return  array   Associative array with "x" and "y" values.
     */
    private function readXY()
    {
        return [
            'x' => $this->readDoubleL(Shapefile::FILE_SHP),
            'y' => $this->readDoubleL(Shapefile::FILE_SHP),
        ];
    }
    
    /**
     * Reads a Z coordinate.
     *
     * @return  array   Associative array with "z" value or empty array.
     */
    private function readZ()
    {
        $z = $this->readDoubleL(Shapefile::FILE_SHP);
        return $this->getOption(Shapefile::OPTION_SUPPRESS_Z) ? [] : ['z' => $z];
    }
    
    /**
     * Reads an M coordinate.
     *
     * @return  array   Associative array with "m" value or empty array.
     */
    private function readM()
    {
        $m = $this->readDoubleL(Shapefile::FILE_SHP);
        return $this->getOption(Shapefile::OPTION_SUPPRESS_M) ? [] : ['m' => $this->parseM($m)];
    }
    
    
    /**
     * Parses an M coordinate according to the ESRI specs:
     * «Any floating point number smaller than –10^38 is considered by a shapefile reader to represent a "no data" value»
     *
     * @param   float   $value  Value to parse.
     *
     * @return  float|bool
     */
    private function parseM($value)
    {
        return ($value <= Shapefile::SHP_NO_DATA_THRESHOLD) ? false : $value;
    }
    
    
    /**
     * Reads an XY bounding box and returns an associative array.
     *
     * @return  array   Associative array with the xmin, xmax, ymin and ymax values.
     */
    private function readXYBoundingBox()
    {
        // Variables are used here because the order of the output array elements is different!
        $xmin = $this->readDoubleL(Shapefile::FILE_SHP);
        $ymin = $this->readDoubleL(Shapefile::FILE_SHP);
        $xmax = $this->readDoubleL(Shapefile::FILE_SHP);
        $ymax = $this->readDoubleL(Shapefile::FILE_SHP);
        return [
            'xmin'  => $xmin,
            'xmax'  => $xmax,
            'ymin'  => $ymin,
            'ymax'  => $ymax,
        ];
    }
    
    /**
     * Reads a Z range and returns an associative array.
     * If flag OPTION_SUPPRESS_Z is set, an empty array will be returned.
     *
     * @return  array   Associative array with the zmin and zmax values.
     */
    private function readZRange()
    {
        $values = [
            'zmin'  => $this->readDoubleL(Shapefile::FILE_SHP),
            'zmax'  => $this->readDoubleL(Shapefile::FILE_SHP),
        ];
        return $this->getOption(Shapefile::OPTION_SUPPRESS_Z) ? [] : $values;
    }
    
    /**
     * Reads an M range and returns an associative array.
     * If flag OPTION_SUPPRESS_M is set, an empty array will be returned.
     *
     * @return  array   Associative array with the mmin and mmax values.
     */
    private function readMRange()
    {
        $values = [
            'mmin'  => $this->parseM($this->readDoubleL(Shapefile::FILE_SHP)),
            'mmax'  => $this->parseM($this->readDoubleL(Shapefile::FILE_SHP)),
        ];
        return $this->getOption(Shapefile::OPTION_SUPPRESS_M) ? [] : $values;
    }
    
    
    /**
     * Returns an empty Geometry depending on the base type of the Shapefile.
     *
     * @return  \Shapefile\Geometry\Geometry
     */
    private function readNull()
    {
        $geometry_classes = [
            Shapefile::SHAPE_TYPE_POINT         => 'Point',
            Shapefile::SHAPE_TYPE_POLYLINE      => 'Linestring',
            Shapefile::SHAPE_TYPE_POLYGON       => 'Polygon',
            Shapefile::SHAPE_TYPE_MULTIPOINT    => 'MultiPoint',
        ];
        $shape_basetype = $this->getBasetype();
        $geometry_class = $geometry_classes[$shape_basetype];
        if ($this->getOption(Shapefile::OPTION_FORCE_MULTIPART_GEOMETRIES) && ($shape_basetype == Shapefile::SHAPE_TYPE_POLYLINE || $shape_basetype == Shapefile::SHAPE_TYPE_POLYGON)) {
            $geometry_class = 'Multi' . $geometry_class;
        }
        $geometry_class = 'Shapefile\Geometry\\' . $geometry_class;
        return new $geometry_class();
    }
    
    
    /**
     * Reads a Point from the SHP file.
     *
     * @return  \Shapefile\Geometry\Point
     */
    private function readPoint()
    {
        return $this->createPoint($this->readXY());
    }
    
    /**
     * Reads a PointM from the SHP file.
     *
     * @return  \Shapefile\Geometry\Point
     */
    private function readPointM()
    {
        return $this->createPoint($this->readXY() + $this->readM());
    }
    
    /**
     * Reads a PointZ from the SHP file.
     *
     * @return  \Shapefile\Geometry\Point
     */
    private function readPointZ()
    {
        return $this->createPoint($this->readXY() + $this->readZ() + $this->readM());
    }
    
    /**
     * Helper method to create the actual Point Geometry using data read from SHP file.
     *
     * @param   array   $data   Array with "x", "y" and optional "z" and "m" values.
     *
     * @return  \Shapefile\Geometry\Point
     */
    private function createPoint($data)
    {
        $Geometry = new Point();
        $Geometry->initFromArray($data);
        return $Geometry;
    }
    
    
    /**
     * Reads a MultiPoint from the SHP file.
     *
     * @param   bool    $flag_return_geometry   Flag to control return type.
     *
     * @return  \Shapefile\Geometry\MultiPoint|array
     */
    private function readMultiPoint($flag_return_geometry = true)
    {
        // Header
        $data = [
            'bbox'      => $this->readXYBoundingBox(),
            'geometry'  => [
                'numpoints' => $this->readInt32L(Shapefile::FILE_SHP),
                'points'    => [],
            ],
        ];
        // Points
        for ($i = 0; $i < $data['geometry']['numpoints']; ++$i) {
            $data['geometry']['points'][] = $this->readXY();
        }
        
        return $flag_return_geometry ? $this->createMultiPoint($data) : $data;
    }
    
    /**
     * Reads a MultiPointM from the SHP file.
     *
     * @return  \Shapefile\Geometry\MultiPoint
     */
    private function readMultiPointM()
    {
        // MultiPoint
        $data = $this->readMultiPoint(false);
        
        // M Range
        $data['bbox'] += $this->readMRange();
        // M Array
        for ($i = 0; $i < $data['geometry']['numpoints']; ++$i) {
            $data['geometry']['points'][$i] += $this->readM();
        }
        
        return $this->createMultiPoint($data);
    }
    
    /**
     * Reads a MultiPointZ from the SHP file.
     *
     * @return  \Shapefile\Geometry\MultiPoint
     */
    private function readMultiPointZ()
    {
        // MultiPoint
        $data = $this->readMultiPoint(false);
        
        // Z Range
        $data['bbox'] += $this->readZRange();
        // Z Array
        for ($i = 0; $i < $data['geometry']['numpoints']; ++$i) {
            $data['geometry']['points'][$i] += $this->readZ();
        }
        
        // M Range
        $data['bbox'] += $this->readMRange();
        // M Array
        for ($i = 0; $i < $data['geometry']['numpoints']; ++$i) {
            $data['geometry']['points'][$i] += $this->readM();
        }
        
        return $this->createMultiPoint($data);
    }
    
    /**
     * Helper method to create the actual MultiPoint Geometry using data read from SHP file.
     *
     * @param   array   $data   Array with "bbox" and "geometry" values.
     *
     * @return  \Shapefile\Geometry\MultiPoint
     */
    private function createMultiPoint($data)
    {
        $Geometry = new MultiPoint();
        $Geometry->initFromArray($data['geometry']);
        if (!$this->getOption(Shapefile::OPTION_IGNORE_GEOMETRIES_BBOXES)) {
            $Geometry->setCustomBoundingBox($data['bbox']);
        }
        return $Geometry;
    }
    
    
    /**
     * Reads a PolyLine from the SHP file.
     *
     * @param   bool    $flag_return_geometry   Flag to control return type.
     *
     * @return  \Shapefile\Geometry\Linestring|\Shapefile\Geometry\MultiLinestring|array
     */
    private function readPolyLine($flag_return_geometry = true)
    {
        // Header
        $data = [
            'bbox'      => $this->readXYBoundingBox(),
            'geometry'  => [
                'numparts'  => $this->readInt32L(Shapefile::FILE_SHP),
                'parts'     => [],
            ],
        ];
        $tot_points = $this->readInt32L(Shapefile::FILE_SHP);
        // Parts
        $parts_first_index = [];
        for ($i = 0; $i < $data['geometry']['numparts']; ++$i) {
            $parts_first_index[$i] = $this->readInt32L(Shapefile::FILE_SHP);
            $data['geometry']['parts'][$i] = [
                'numpoints' => 0,
                'points'    => [],
            ];
        }
        // Points
        $part = 0;
        for ($i = 0; $i < $tot_points; ++$i) {
            if (isset($parts_first_index[$part + 1]) && $parts_first_index[$part + 1] == $i) {
                ++$part;
            }
            $data['geometry']['parts'][$part]['points'][] = $this->readXY();
        }
        for ($i = 0; $i < $data['geometry']['numparts']; ++$i) {
            $data['geometry']['parts'][$i]['numpoints'] = count($data['geometry']['parts'][$i]['points']);
        }
        
        return $flag_return_geometry ? $this->createLinestring($data) : $data;
    }
    
    /**
     * Reads a PolyLineM from the SHP file.
     *
     * @param   bool    $flag_return_geometry   Flag to control return type.
     *
     * @return  \Shapefile\Geometry\Linestring|\Shapefile\Geometry\MultiLinestring|array
     */
    private function readPolyLineM($flag_return_geometry = true)
    {
        // PolyLine
        $data = $this->readPolyLine(false);
        
        // M Range
        $data['bbox'] += $this->readMRange();
        // M Array
        for ($i = 0; $i < $data['geometry']['numparts']; ++$i) {
            for ($k = 0; $k < $data['geometry']['parts'][$i]['numpoints']; ++$k) {
                $data['geometry']['parts'][$i]['points'][$k] += $this->readM();
            }
        }
        
        return $flag_return_geometry ? $this->createLinestring($data) : $data;
    }
    
    /**
     * Reads a PolyLineZ from the SHP file.
     *
     * @param   bool    $flag_return_geometry   Flag to control return type.
     *
     * @return  \Shapefile\Geometry\Linestring|\Shapefile\Geometry\MultiLinestring|array
     */
    private function readPolyLineZ($flag_return_geometry = true)
    {
        // PolyLine
        $data = $this->readPolyLine(false);
        
        // Z Range
        $data['bbox'] += $this->readZRange();
        // Z Array
        for ($i = 0; $i < $data['geometry']['numparts']; ++$i) {
            for ($k = 0; $k < $data['geometry']['parts'][$i]['numpoints']; ++$k) {
                $data['geometry']['parts'][$i]['points'][$k] += $this->readZ();
            }
        }
        
        // M Range
        $data['bbox'] += $this->readMRange();
        // M Array
        for ($i = 0; $i < $data['geometry']['numparts']; ++$i) {
            for ($k = 0; $k < $data['geometry']['parts'][$i]['numpoints']; ++$k) {
                $data['geometry']['parts'][$i]['points'][$k] += $this->readM();
            }
        }
        
        return $flag_return_geometry ? $this->createLinestring($data) : $data;
    }
    
    /**
     * Helper method to create the actual Linestring Geometry using data read from SHP file.
     * If OPTION_FORCE_MULTIPART_GEOMETRIES is set, a MultiLinestring is returned instead.
     *
     * @param   array   $data   Array with "bbox" and "geometry" values.
     *
     * @return  \Shapefile\Geometry\Linestring|\Shapefile\Geometry\MultiLinestring
     */
    private function createLinestring($data)
    {
        if (!$this->getOption(Shapefile::OPTION_FORCE_MULTIPART_GEOMETRIES) && $data['geometry']['numparts'] == 1) {
            $data['geometry'] = $data['geometry']['parts'][0];
            $Geometry = new Linestring();
        } else {
            $Geometry = new MultiLinestring();
        }
        $Geometry->initFromArray($data['geometry']);
        if (!$this->getOption(Shapefile::OPTION_IGNORE_GEOMETRIES_BBOXES)) {
            $Geometry->setCustomBoundingBox($data['bbox']);
        }
        return $Geometry;
    }
    
    
    /**
     * Reads a Polygon from the SHP file.
     *
     * @return  \Shapefile\Geometry\Polygon|\Shapefile\Geometry\MultiPolygon
     */
    private function readPolygon()
    {
        return $this->createPolygon($this->readPolyLine(false));
    }
    
    /**
     * Reads a PolygonM from the SHP file.
     *
     * @return  \Shapefile\Geometry\Polygon|\Shapefile\Geometry\MultiPolygon
     */
    private function readPolygonM()
    {
        return $this->createPolygon($this->readPolyLineM(false));
    }
    
    /**
     * Reads a PolygonZ from the SHP file.
     *
     * @return  \Shapefile\Geometry\Polygon|\Shapefile\Geometry\MultiPolygon
     */
    private function readPolygonZ()
    {
        return $this->createPolygon($this->readPolyLineZ(false));
    }
    
    /**
     * Helper method to create the actual Polygon Geometry using data read from SHP file.
     * If OPTION_FORCE_MULTIPART_GEOMETRIES is set, a MultiPolygon is returned instead.
     *
     * @param   array   $data   Array with "bbox" and "geometry" values.
     *
     * @return  \Shapefile\Geometry\Polygon|\Shapefile\Geometry\MultiPolygon
     */
    private function createPolygon($data)
    {
        $MultiPolygon   = new MultiPolygon(null, $this->getOption(Shapefile::OPTION_POLYGON_CLOSED_RINGS_ACTION), $this->getOption(Shapefile::OPTION_POLYGON_OUTPUT_ORIENTATION));
        $Polygon        = null;
        $temp_state     = null;
        foreach ($data['geometry']['parts'] as $part) {
            $Linestring = new Linestring();
            $Linestring->initFromArray($part);
            $is_clockwise = $Linestring->isClockwise();
            if ($Polygon === null && !$is_clockwise && !$this->getOption(Shapefile::OPTION_POLYGON_ORIENTATION_READING_AUTOSENSE)) {
                throw new ShapefileException(Shapefile::ERR_GEOM_POLYGON_WRONG_ORIENTATION);
            }
            if ($temp_state === null || $temp_state === $is_clockwise) {
                if ($Polygon !== null) {
                    $MultiPolygon->addPolygon($Polygon);
                }
                $Polygon    = new Polygon(null, $this->getOption(Shapefile::OPTION_POLYGON_CLOSED_RINGS_ACTION), $this->getOption(Shapefile::OPTION_POLYGON_OUTPUT_ORIENTATION));
                $temp_state = $is_clockwise;
            }
            $Polygon->addRing($Linestring);
        }
        $MultiPolygon->addPolygon($Polygon);
        
        $Geometry = (!$this->getOption(Shapefile::OPTION_FORCE_MULTIPART_GEOMETRIES) && $MultiPolygon->getNumPolygons() == 1) ? $MultiPolygon->getPolygon(0) : $MultiPolygon;
        if (!$this->getOption(Shapefile::OPTION_IGNORE_GEOMETRIES_BBOXES)) {
            $Geometry->setCustomBoundingBox($data['bbox']);
        }
        return $Geometry;
    }
}
