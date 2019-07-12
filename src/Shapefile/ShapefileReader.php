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

namespace Shapefile;

use Shapefile\Geometry\Point;
use Shapefile\Geometry\MultiPoint;
use Shapefile\Geometry\Linestring;
use Shapefile\Geometry\MultiLinestring;
use Shapefile\Geometry\Polygon;
use Shapefile\Geometry\MultiPolygon;

class ShapefileReader extends Shapefile implements \Iterator
{
    /**
     * @var array   Array of files. Every files is represented as an associative array:
     *                  "xxx" => [
     *                      "handle" => resource
     *                      "size"   => integer
     *                  ]
     */
    private $files = [];
    
    
    /**
     * @var array   DBF field names map: fields are numerically indexed into DBF files.
     */
    private $dbf_fields = [];
    
    /**
     * @var integer DBF file header size in bytes.
     */
    private $dbf_header_size;
    
    /**
     * @var integer DBF file record size in bytes.
     */
    private $dbf_record_size;
    
    
    /**
     * @var bool    Flag used by readDoubleL() method.
     */
    private $big_endian_machine;
    
    
    /**
     * @var integer Pointer to current SHP and DBF files record.
     */
    private $current_record;
    
    /**
     * @var integer Total number of records in SHP and DBF files.
     */
    private $tot_records;
    
    
    
    /////////////////////////////// PUBLIC ///////////////////////////////
    /**
     * Constructor.
     * 
     * @param   string|array    $files      Path to SHP file or array of paths or handles of individual files.
     * @param   array           $options    Optional associative array of options.
     */
    public function __construct($files, $options = [])
    {
        // Files
        if (is_string($files)) {
            $basename = (substr($files, -4) == '.' . Shapefile::FILE_SHP) ? substr($files, 0, -4) : $files;
            $files = [
                Shapefile::FILE_SHP => $basename . '.' . Shapefile::FILE_SHP,
                Shapefile::FILE_SHX => $basename . '.' . Shapefile::FILE_SHX,
                Shapefile::FILE_DBF => $basename . '.' . Shapefile::FILE_DBF,
                Shapefile::FILE_DBT => $basename . '.' . Shapefile::FILE_DBT,
                Shapefile::FILE_PRJ => $basename . '.' . Shapefile::FILE_PRJ,
                Shapefile::FILE_CPG => $basename . '.' . Shapefile::FILE_CPG,
                Shapefile::FILE_CST => $basename . '.' . Shapefile::FILE_CST,
            ];
        } else {
            if (!isset($files[Shapefile::FILE_SHP], $files[Shapefile::FILE_SHX], $files[Shapefile::FILE_DBF])) {
                throw new ShapefileException(Shapefile::ERR_FILE_MISSING, strtoupper(implode(', ', [Shapefile::FILE_SHP, Shapefile::FILE_SHX, Shapefile::FILE_DBF])));
            }
        }
        if (array_filter($files, 'is_resource') === $files) {
            foreach ($files as $type => $file) {
                $this->files[$type] = [
                    'handle'    => $file,
                    'size'      => fstat($file)['size'],
                ];
            }
        } else {
            foreach ([
                ['type' => Shapefile::FILE_SHP, 'required' => true],
                ['type' => Shapefile::FILE_SHX, 'required' => true],
                ['type' => Shapefile::FILE_DBF, 'required' => true],
                ['type' => Shapefile::FILE_DBT, 'required' => false],
                ['type' => Shapefile::FILE_PRJ, 'required' => false],
                ['type' => Shapefile::FILE_CPG, 'required' => false],
                ['type' => Shapefile::FILE_CST, 'required' => false],
            ] as $f) { 
                if (isset($files[$f['type']])) {
                    if (is_string($files[$f['type']]) && is_readable($files[$f['type']]) && is_file($files[$f['type']])) {
                        $this->files[$f['type']] = $this->openFile($files[$f['type']]);
                    } elseif ($f['required']) {
                        throw new ShapefileException(Shapefile::ERR_FILE_EXISTS, $files[$f['type']]);
                    }
                }
            }
        }
        
        // Options
        $this->initOptions([
            Shapefile::OPTION_INVERT_POLYGONS_ORIENTATION,
            Shapefile::OPTION_SUPPRESS_Z,
            Shapefile::OPTION_SUPPRESS_M,
            Shapefile::OPTION_DBF_FORCE_ALL_CAPS,
            Shapefile::OPTION_DBF_NULL_PADDING_CHAR,
            Shapefile::OPTION_FORCE_MULTIPART_GEOMETRIES,
            Shapefile::OPTION_IGNORE_SHAPEFILE_BBOX,
            Shapefile::OPTION_IGNORE_GEOMETRIES_BBOXES,
            Shapefile::OPTION_DBF_IGNORED_FIELDS,
            Shapefile::OPTION_DBF_NULLIFY_INVALID_DATES,
        ], $options);
        
        // Misc
        $this->big_endian_machine = current(unpack('v', pack('S', 0xff))) !== 0xff;
        $this->tot_records = ($this->files[Shapefile::FILE_SHX]['size'] - 100) / 8;
        
        // PRJ
        if (isset($this->files[Shapefile::FILE_PRJ])) {
            $this->setPRJ($this->readString(Shapefile::FILE_PRJ, $this->files[Shapefile::FILE_PRJ]['size']));
        }
        
        // CPG and CST (DBF charset): CPG file takes precedence over CST one
        if (isset($this->files[Shapefile::FILE_CPG])) {
            $this->setDBFCharset($this->readString(Shapefile::FILE_CPG, $this->files[Shapefile::FILE_CPG]['size']));
        } elseif (isset($this->files[Shapefile::FILE_CST])) {
            $this->setDBFCharset($this->readString(Shapefile::FILE_CST, $this->files[Shapefile::FILE_CST]['size']));
        }
        
        // Read headers
        $this->readSHPHeader();
        $this->readDBFHeader();
        
        // Init record pointer
        $this->rewind();
    }
    
    /**
     * Destructor.
     * 
     * Closes all file pointer resource handles.
     */
    public function __destruct()
    {
        foreach ($this->files as $file) {
            $this->closeFile($file['handle']);
        }
    }
    
    
    public function rewind()
    {
        $this->current_record = 0;
        $this->next();
    }
    
    public function next()
    {
        ++$this->current_record;
        if (!$this->checkRecordIndex($this->current_record)) {
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
     * Gets total number of records in SHP and DBF files.
     *
     * @return  integer
     */
    public function getTotRecords()
    {
        return $this->tot_records;
    }
    
    /**
     * Gets current record index.
     *
     * Note that records count starts from 1 in Shapefiles.
     * When the last record is reached, the special value ShapeFile::EOF will be returned.
     *
     * @return  integer
     */
    public function getCurrentRecord()
    {
        return $this->current_record;
    }
    
    /**
     * Sets current record index. Throws an exception if provided index is out of range.
     *
     * @param   integer $index   Index of the record to select.
     */
    public function setCurrentRecord($index)
    {
        if (!$this->checkRecordIndex($index)) {
            throw new ShapefileException(Shapefile::ERR_INPUT_RECORD_NOT_FOUND, $index);
        }
        $this->current_record = $index;
    }
    
    
    /**
     * Gets current record and moves the cursor to the next one.
     *
     * @return  AbstractGeometry
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
     * Opens a file in binary read mode returning a resource handle and its size.
     *
     * @param   string  $file       Path to the file to open.
     *
     * @return  array       Associative array with "handle" (file pointer resource) and "size" (file size in bytes)
     */
    private function openFile($file)
    {
        $handle = fopen($file, 'rb');
        if (!$handle) {
            throw new ShapefileException(Shapefile::ERR_FILE_OPEN, $file);
        }
        return [
            'handle'    => $handle,
            'size'      => fstat($handle)['size'],
        ];
    }
    
    /**
     * Closes a previously opened resource handle.
     *
     * @param   resource    $handle     The resource handle of the file.
     */
    private function closeFile($handle)
    {
        if ($handle) {
            fclose($handle);
        }
    }
    
    /**
     * Sets the pointer position of a resource handle to specified value.
     *
     * @param   string  $file_type  File type (member of $this->files array).
     * @param   integer $position   The position to set the pointer to.
     */
    private function setFilePointer($file_type, $position)
    {
        fseek($this->files[$file_type]['handle'], $position, SEEK_SET);
    }
    
    /**
     * Increase the pointer position of a resource handle of specified value.
     *
     * @param   string  $file_type  File type (member of $this->files array).
     * @param   integer $offset     The offset to move the pointer for.
     */
    private function setFileOffset($file_type, $offset)
    {
        fseek($this->files[$file_type]['handle'], $offset, SEEK_CUR);
    }
    
    
    /**
     * Reads data from a resource handle and unpacks it according to the given format.
     *
     * @param   string  $file_type          File type (member of $this->files array).
     * @param   string  $format             Format code. See php pack() documentation.
     * @param   integer $length             Number of bytes to read.
     * @param   bool    $invert_endianness  Set this optional flag to true when reading floating point numbers on a big endian machine.
     *
     * @return  mixed
     */
    private function readData($file_type, $format, $length, $invert_endianness = false)
    {
        $data = fread($this->files[$file_type]['handle'], $length);
        if ($data === false) {
            return null;
        }
        if ($invert_endianness) {
            $data = strrev($data);
        }
        return current(unpack($format, $data));
    }
    
    /**
     * Reads an unsigned short, 16 bit, little endian byte order, from a resource handle.
     *
     * @param   string  $file_type      File type (member of $this->files array).
     *
     * @return  integer
     */
    private function readInt16L($file_type)
    {
        return $this->readData($file_type, 'v', 2);
    }
    
    /**
     * Reads an unsigned long, 32 bit, big endian byte order, from a resource handle.
     *
     * @param   string  $file_type      File type (member of $this->files array).
     *
     * @return  integer
     */
    private function readInt32B($file_type)
    {
        return $this->readData($file_type, 'N', 4);
    }
    
    /**
     * Reads an unsigned long, 32 bit, little endian byte order, from a resource handle.
     *
     * @param   string  $file_type      File type (member of $this->files array).
     *
     * @return  integer
     */
    private function readInt32L($file_type)
    {
        return $this->readData($file_type, 'V', 4);
    }
    
    /**
     * Reads a double, 64 bit, little endian byte order, from a resource handle.
     *
     * @param   string  $file_type      File type (member of $this->files array).
     *
     * @return  double
     */
    private function readDoubleL($file_type)
    {
        return $this->readData($file_type, 'd', 8, $this->big_endian_machine);
    }
    
    /**
     * Reads a string of given length from a resource handle and converts it to UTF-8.
     *
     * @param   string  $file_type      File type (member of $this->files array).
     * @param   integer $length         Length of the string to read.
     * @param   string  $charset        Optional input charset of the string.
     *
     * @return  string
     */
    private function readString($file_type, $length, $charset = Shapefile::DEFAULT_DBF_CHARSET)
    {
        $ret = @iconv($charset, 'UTF-8', $this->readData($file_type, 'A*', $length));
        if ($ret === false) {
            throw new ShapefileException(Shapefile::ERR_DBF_CHARSET_CONVERSION);
        }
        return trim($ret);
    }
    
    /**
     * Reads an unsigned char from a resource handle.
     *
     * @param   string  $file_type      File type (member of $this->files array).
     *
     * @return  string
     */
    private function readChar($file_type)
    {
        return $this->readData($file_type, 'C', 1);
    }
    
    
    /**
     * Checks whether a record index value is valid or not.
     *
     * @param   integer $index      The index value to check.
     *
     * @return  bool
     */
    private function checkRecordIndex($index)
    {
        return ($index > 0 && $index <= $this->tot_records);
    }
    
    
    /**
     * Reads SHP file header.
     */
    private function readSHPHeader()
    {
        // Shape Type
        $this->setFilePointer(Shapefile::FILE_SHP, 32);
        $this->setShapeType($this->readInt32L(Shapefile::FILE_SHP));
        
        // Bounding Box (Z and M ranges are always present in the Shapefile, although with a 0 value if not used) 
        if (!$this->getOption(Shapefile::OPTION_IGNORE_SHAPEFILE_BBOX)) {
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
    }
    
    /**
     * Reads DBF file header.
     */
    private function readDBFHeader()
    {
        // Number of records
        $this->setFilePointer(Shapefile::FILE_DBF, 4);
        if ($this->readInt32L(Shapefile::FILE_DBF) !== $this->tot_records) {
            throw new ShapefileException(Shapefile::ERR_DBF_MISMATCHED_FILE);
        }
        
        // Header and Record size
        $this->dbf_header_size = $this->readInt16L(Shapefile::FILE_DBF);
        $this->dbf_record_size = $this->readInt16L(Shapefile::FILE_DBF);
        
        // Fields
        $this->dbf_fields = [];
        $this->setFilePointer(Shapefile::FILE_DBF, 32);
        while (ftell($this->files['dbf']['handle']) < $this->dbf_header_size - 1) {
            $name       = $this->readString(Shapefile::FILE_DBF, 11);
            $type       = $this->readString(Shapefile::FILE_DBF, 1);
            $this->setFileOffset(Shapefile::FILE_DBF, 4);
            $size       = $this->readChar(Shapefile::FILE_DBF);
            $decimals   = $this->readChar(Shapefile::FILE_DBF);
            $ignored    = in_array($name, $this->getOption(Shapefile::OPTION_DBF_IGNORED_FIELDS));
            if ($type === Shapefile::DBF_TYPE_MEMO && !$flag_ignore && !isset($files[Shapefile::FILE_DBT])) {
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
    }
    
    
    /**
     * Reads current record in both SHP and DBF files and returns a Geometry.
     *
     * @return  AbstractGeometry
     */
    private function readCurrentRecord()
    {
        if (!$this->valid()) {
            return false;
        }
        
        // Read SHP offset from SHX
        $this->setFilePointer(Shapefile::FILE_SHX, 100 + (($this->current_record - 1) * 8));
        $shp_offset = $this->readInt32B(Shapefile::FILE_SHX) * 2;
        $this->setFilePointer(Shapefile::FILE_SHP, $shp_offset);
        
        // Read SHP record header
        $this->setFileOffset(Shapefile::FILE_SHP, 8);
        $shape_type = $this->readInt32L(Shapefile::FILE_SHP);
        if ($shape_type != Shapefile::SHAPE_TYPE_NULL && $shape_type != $this->getShapeType()) {
            throw new ShapefileException(Shapefile::ERR_SHP_WRONG_RECORD_TYPE, $shape_type);
        }
        
        // Read Geometry
        $methods = [
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
        $Geometry = $this->{$methods[$shape_type]}();
        $this->addGeometry($Geometry);
        
        // Read DBF data
        $this->setFilePointer(Shapefile::FILE_DBF, $this->dbf_header_size + (($this->current_record - 1) * $this->dbf_record_size));
        // Check if DBF is not corrupted (some "naive" users try to edit the DBF separately...)
        // Some GIS do not include the last Shapefile::DBF_EOF_MARKER (0x1a) byte in the DBF file, hence the "+ 1" in the following line
        if (ftell($this->files['dbf']['handle']) >= ($this->files['dbf']['size'] - $this->dbf_record_size + 1)) {
            throw new ShapefileException(Shapefile::ERR_DBF_EOF_REACHED);
        }
        $Geometry->setFlagDeleted($this->readChar(Shapefile::FILE_DBF) !== Shapefile::DBF_BLANK);
        foreach ($this->dbf_fields as $i => $f) {
            if ($f['ignored']) {
                $this->setFileOffset(Shapefile::FILE_DBF, $f['size']);
            } else {
                $value = $this->decodeFieldValue($f['name'], $this->readString(Shapefile::FILE_DBF, $f['size'], $this->getDBFCharset()));
                $Geometry->setData($f['name'], $value);
            }
        }
        
        return $Geometry;
    }
    
    
    /**
     * Decodes a raw value read from a DBF field.
     *
     * @param   string  $field      Name of the field.
     * @param   string  $value      Raw value to decode.
     *
     * @return  mixed
     */
    private function decodeFieldValue($field, $value)
    {
        if ($this->getOption(Shapefile::OPTION_DBF_NULL_PADDING_CHAR) !== null && $value == str_repeat($this->getOption(Shapefile::OPTION_DBF_NULL_PADDING_CHAR), $this->getFieldSize($field))) {
            $value = null;
        } else {
            switch ($this->getFieldType($field)) {
                case Shapefile::DBF_TYPE_DATE:
                    $DateTime   = \DateTime::createFromFormat('Ymd', $value);
                    $errors     = \DateTime::getLastErrors();
                    if ($errors['warning_count'] || $errors['error_count']) {
                        $value = $this->getOption(Shapefile::OPTION_DBF_NULLIFY_INVALID_DATES) ? null : $value;
                    } else {
                        $value = $DateTime->format('Y-m-d');
                    }  
                    break;
                    
                case Shapefile::DBF_TYPE_LOGICAL:
                    $value = ($value === '?') ? null : in_array($value, ['Y', 'y', 'T', 't']);
                break;
                
                case Shapefile::DBF_TYPE_MEMO:
                    $this->setFilePointer(Shapefile::FILE_DBT, intval($value) * Shapefile::DBT_BLOCK_SIZE);
                    $value = '';
                    do {
                        if (ftell($this->files['dbt']['handle']) >= $this->files['dbt']['size']) {
                            throw new ShapefileException(Shapefile::ERR_DBT_EOF_REACHED);
                        }
                        $value .= $this->readString(Shapefile::FILE_DBT, Shapefile::DBT_BLOCK_SIZE, $this->getDBFCharset());
                    } while (ord(substr($value, -1)) != Shapefile::DBT_FIELD_TERMINATOR && ord(substr($value, -2, 1)) != Shapefile::DBT_FIELD_TERMINATOR);
                    $value = substr($value, 0, -2); 
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
     * @return  float|bool
     */
    private function parseM($value)
    {
        return ($value < -pow(10, 38)) ? false : $value;
    }
    
    
    /**
     * Reads an XY bounding box and returns an associative array.
     *
     * @return  array   Associative array with the xmin, xmax, ymin and ymax values.
     */
    private function readXYBoundingBox()
    {
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
     * @return  AbstractGeometry
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
        if ($this->getOption(Shapefile::OPTION_FORCE_MULTIPART_GEOMETRIES) && $shape_basetype != Shapefile::SHAPE_TYPE_MULTIPOINT) {
            $geometry_class = 'Multi' . $geometry_class;
        }
        $geometry_class = 'Shapefile\Geometry\\' . $geometry_class;
        return new $geometry_class();
    }
    
    
    /**
     * Reads a Point from the SHP file.
     *
     * @return  Point
     */
    private function readPoint()
    {
        return $this->createPoint($this->readXY());
    }
    
    /**
     * Reads a PointM from the SHP file.
     *
     * @return  Point
     */
    private function readPointM()
    {
        return $this->createPoint($this->readXY() + $this->readM());
    }
    
    /**
     * Reads a PointZ from the SHP file.
     *
     * @return  Point
     */
    private function readPointZ()
    {
        return $this->createPoint($this->readXY() + $this->readZ() + $this->readM());
    }
    
    /**
     * Helper method to create the actual Point Geometry using data read from SHP file.
     * If OPTION_FORCE_MULTIPART_GEOMETRIES is set, a MultiPoint is returned instead.
     *
     * @param   array   $data   Array with "x", "y" and optional "z" and "m" values.
     *
     * @return  Point|MultiPoint
     */
    private function createPoint($data)
    {
        if ($this->getOption(Shapefile::OPTION_FORCE_MULTIPART_GEOMETRIES)) {
            $data = [
                'numpoints' => 1,
                'points'    => [$data],
            ];
            $Geometry = new MultiPoint();
        } else {
            $Geometry = new Point();
        }
        $Geometry->initFromArray($data);
        return $Geometry;
    }
    
    
    /**
     * Reads a MultiPoint from the SHP file.
     *
     * @param   bool    $flag_return_geometry   Flag to control return type.
     *
     * @return  MultiPoint|array
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
     * @return  MultiPoint
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
     * @return  MultiPoint
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
     * @return  MultiPoint
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
     * @return  Linestring|MultiLinestring|array
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
     * @return  Linestring|MultiLinestring|array
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
     * @return  Linestring|MultiLinestring|array
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
     * @return  Linestring|MultiLinestring
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
     * @return  Polygon|MultiPolygon
     */
    private function readPolygon()
    {
        return $this->createPolygon($this->readPolyLine(false));
    }
    
    /**
     * Reads a PolygonM from the SHP file.
     *
     * @return  Polygon|MultiPolygon
     */
    private function readPolygonM()
    {
        return $this->createPolygon($this->readPolyLineM(false));
    }
    
    /**
     * Reads a PolygonZ from the SHP file.
     *
     * @return  Polygon|MultiPolygon
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
     * @return  Polygon|MultiPolygon
     */
    private function createPolygon($data)
    {
        // Parse Polygon
        $i      = -1;
        $parts  = [];
        foreach ($data['geometry']['parts'] as $rawpart) {
            if ($this->isClockwise($rawpart['points'])) {
                ++$i;
                $parts[$i] = [
                    'numrings'  => 0,
                    'rings'     => [],
                ];
            }
            if ($i < 0) {
                throw new ShapefileException(Shapefile::ERR_GEOM_POLYGON_NOT_VALID);
            }
            if ($this->getOption(Shapefile::OPTION_INVERT_POLYGONS_ORIENTATION)) {
                $rawpart['points'] = array_reverse($rawpart['points']);
            }
            $parts[$i]['rings'][] = $rawpart;
        }
        for ($i = 0; $i < count($parts); ++$i) {
            $parts[$i]['numrings'] = count($parts[$i]['rings']);
        }
        $data = [
            'bbox'      => $data['bbox'],
            'geometry'  => [
                'numparts'  => count($parts),
                'parts'     => $parts,
            ],
        ];
        
        // Create Geometry
        if (!$this->getOption(Shapefile::OPTION_FORCE_MULTIPART_GEOMETRIES) && $data['geometry']['numparts'] == 1) {
            $data['geometry'] = $data['geometry']['parts'][0];
            $Geometry = new Polygon();
        } else {
            $Geometry = new MultiPolygon();
        }
        $Geometry->initFromArray($data['geometry']);
        if (!$this->getOption(Shapefile::OPTION_IGNORE_GEOMETRIES_BBOXES)) {
            $Geometry->setCustomBoundingBox($data['bbox']);
        }
        return $Geometry;  
    }
    
    /**
     * Checks whether a ring is clockwise or not.
     * It uses Gauss's area formula to check for positive or negative orientation.
     *
     * An optional $exp parameter is used in order to deal with small polygons.
     *
     * @param   array   $points     Array of points. Each element must have a "x" and "y" member.
     * @param   integer $exp        Optional exponent to deal with small areas.
     *
     * @return  bool
     */
    private function isClockwise($points, $exp = 1)
    {
        $num_points = count($points);
        if ($num_points < 2) {
            return true;
        }
        
        $num_points--;
        $tot = 0;
        for ($i = 0; $i < $num_points; ++$i) {
            $tot += ($exp * $points[$i]['x'] * $points[$i+1]['y']) - ($exp * $points[$i]['y'] * $points[$i+1]['x']);
        }
        $tot += ($exp * $points[$num_points]['x'] * $points[0]['y']) - ($exp * $points[$num_points]['y'] * $points[0]['x']);
        
        if ($tot == 0) {
            if ($exp >= pow(10, 9)) {
                throw new ShapefileException(Shapefile::ERR_GEOM_POLYGON_AREA_TOO_SMALL);
            }
            return $this->isClockwise($points, $exp * pow(10, 3));
        }
        
        return $tot < 0;
    }
    
}
