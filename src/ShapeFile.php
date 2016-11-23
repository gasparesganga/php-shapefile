<?php
/***************************************************************************************************
ShapeFile - PHP library to read any ESRI Shapefile and its associated DBF into a PHP Array or WKT
    Author          : Gaspare Sganga
    Version         : 2.2.0
    License         : MIT
    Documentation   : http://gasparesganga.com/labs/php-shapefile
****************************************************************************************************/

namespace ShapeFile;

class ShapeFile implements \Iterator
{
    // Constructor flags
    const FLAG_SUPPRESS_Z   = 0b1;
    const FLAG_SUPPRESS_M   = 0b10;
    // getShapeType() return type
    const FORMAT_INT        = 0;
    const FORMAT_STR        = 1;
    // getRecord() Geometry format
    const GEOMETRY_ARRAY    = 0;
    const GEOMETRY_WKT      = 1;
    const GEOMETRY_BOTH     = 2;
    // End of file
    const EOF               = 0;
    
    private static $error_messages = array(
        'FILE_EXISTS'               => array(11, "File not found. Check if the file exists and is readable"),
        'FILE_OPEN'                 => array(12, "Unable to read file"),
        'SHAPE_TYPE_NOT_SUPPORTED'  => array(21, "Shape Type not supported"),
        'WRONG_RECORD_TYPE'         => array(22, "Wrong Record's Shape Type"),
        'POLYGON_AREA_TOO_SMALL'    => array(31, "Polygon Area too small, can't determine vertex orientation"),
        'POLYGON_NOT_VALID'         => array(32, "Polygon not valid or Polygon Area too small. Please check the geometries before reading the Shapefile"),
        'DBF_FILE_NOT_VALID'        => array(41, "DBF file doesn't seem to be a valid dBase III or dBase IV format"),
        'DBF_MISMATCHED_FILE'       => array(42, "Mismatched DBF file. Number of records not corresponding to the SHP file"),
        'DBF_EOF_REACHED'           => array(43, "End of DBF file reached. Number of records not corresponding to the SHP file"),
        'RECORD_INDEX_NOT_VALID'    => array(91, "Record index not valid. Check the total number of records in the SHP file")
    ); 
    private static $shape_types = array(
        0   => 'Null Shape',
        1   => 'Point',
        3   => 'PolyLine',
        5   => 'Polygon',
        8   => 'MultiPoint',
        11  => 'PointZ',
        13  => 'PolyLineZ',
        15  => 'PolygonZ',
        18  => 'MultiPointZ',
        21  => 'PointM',
        23  => 'PolyLineM',
        25  => 'PolygonM',
        28  => 'MultiPointM'
    );
    
    // Handles
    private $shp_handle;
    private $shx_handle;
    private $dbf_handle;
    // File sizes
    private $shp_size;
    private $shx_size;
    private $dbf_size;
    
    // Shape info
    private $bounding_box;
    private $shape_type;
    private $prj;
    
    // DBF
    private $dbf_fields;
    private $dbf_header_size;
    private $dbf_record_size;
    
    // Misc
    private $flags;
    private $big_endian_machine;
    private $current_record;
    private $tot_records;
    
    
    public function __construct($files, $flags = 0)
    {
        // Files
        if (is_string($files)) {
            $basename = (substr($files, -4) == '.shp') ? substr($files, 0, -4) : $files;
            $shp_file = $basename.'.shp';
            $shx_file = $basename.'.shx';
            $dbf_file = $basename.'.dbf';
            $prj_file = $basename.'.prj';
        } else {
            $shp_file = isset($files['shp']) ? $files['shp'] : '';
            $shx_file = isset($files['shx']) ? $files['shx'] : '';
            $dbf_file = isset($files['dbf']) ? $files['dbf'] : '';
            $prj_file = isset($files['prj']) ? $files['prj'] : '';
        }
        $this->shp_handle = $this->openFile($shp_file);
        $this->shx_handle = $this->openFile($shx_file);
        $this->dbf_handle = $this->openFile($dbf_file);
        $this->shp_size   = filesize($shp_file);
        $this->shx_size   = filesize($shx_file);
        $this->dbf_size   = filesize($dbf_file);
        $this->prj        = (is_readable($prj_file) && is_file($prj_file)) ? file_get_contents($prj_file) : null;
        
        // Flags
        $this->flags = array(
            self::FLAG_SUPPRESS_Z   => ($flags & self::FLAG_SUPPRESS_Z) > 0,
            self::FLAG_SUPPRESS_M   => ($flags & self::FLAG_SUPPRESS_M) > 0
        );
        
        // Misc
        $this->big_endian_machine   = current(unpack('v', pack('S', 0xff))) !== 0xff;
        $this->tot_records          = ($this->shx_size - 100) / 8;
        
        // Read Headers
        $this->readSHPHeader();
        $this->readDBFHeader();
        
        // Init record pointer
        $this->rewind();
    }
    
    public function __destruct()
    {
        $this->closeFile($this->shp_handle);
        $this->closeFile($this->shx_handle);
        $this->closeFile($this->dbf_handle);
    }
    
    
    public function rewind()
    {
        $this->current_record = 0;
        $this->next();
    }
    
    public function next()
    {
        $this->current_record++;
        if (!$this->checkRecordIndex($this->current_record)) $this->current_record = self::EOF;
    }
    
    public function current()
    {
        return $this->readSHPRecord();
    }

    public function key()
    {
        return $this->current_record;
    }

    public function valid()
    {
        return ($this->current_record !== self::EOF);
    }
    
    
    public function getShapeType($format = self::FORMAT_INT)
    {
        if ($format == self::FORMAT_STR) {
            return self::$shape_types[$this->shape_type];
        } else {
            return $this->shape_type;
        }
    }
    
    public function getBoundingBox()
    {
        return $this->bounding_box;
    }
    
    public function getPRJ()
    {
        return $this->prj;
    }
    
    public function getDBFFields()
    {
        return $this->dbf_fields;
    }
    
    
    public function getTotRecords()
    {
        return $this->tot_records;
    }
    
    public function getCurrentRecord()
    {
        return $this->current_record;
    }
    
    public function setCurrentRecord($index)
    {
        if (!$this->checkRecordIndex($index)) $this->throwException('RECORD_INDEX_NOT_VALID', $index);
        $this->current_record = $index;
    }
    
    public function getRecord($geometry_format = self::GEOMETRY_BOTH)
    {
        $ret = $this->readSHPRecord($geometry_format);
        if ($ret !== false) $this->next();
        return $ret;
    }
    
    
    /****************************** PRIVATE ******************************/
    private function openFile($file)
    {
        if (!(is_readable($file) && is_file($file))) $this->throwException('FILE_EXISTS', $file);
        $handle = fopen($file, 'rb');
        if (!$handle) $this->throwException('FILE_OPEN', $file);
        return $handle;
    }
    
    private function closeFile($file)
    {
        if ($file) fclose($file);
    }
    
    private function setFilePointer($handle, $position)
    {
        fseek($handle, $position, SEEK_SET);
    }
    
    private function setFileOffset($handle, $offset)
    {
        fseek($handle, $offset, SEEK_CUR);
    }
    
    
    private function readData($handle, $type, $length, $invert_endianness = false)
    {
        $data = fread($handle, $length);
        if (!$data) return null;
        if ($invert_endianness) $data = strrev($data);
        return current(unpack($type, $data));
    }
    
    private function readInt16L($handle)
    {
        return $this->readData($handle, 'v', 2);
    }
    
    private function readInt32B($handle)
    {
        return $this->readData($handle, 'N', 4);
    }
    
    private function readInt32L($handle)
    {
        return $this->readData($handle, 'V', 4);
    }
    
    private function readDoubleL($handle)
    {
        return $this->readData($handle, 'd', 8, $this->big_endian_machine);
    }
    
    private function readString($handle, $length)
    {
        return utf8_encode(trim($this->readData($handle, 'A*', $length)));
    }
    
    private function readChar($handle)
    {
        return $this->readData($handle, 'C', 1);
    }
    
    
    private function readSHPHeader()
    {
        // Shape Type
        $this->setFilePointer($this->shp_handle, 32);
        $this->shape_type = $this->readInt32L($this->shp_handle);
        if (!isset(self::$shape_types[$this->shape_type])) $this->throwException('SHAPE_TYPE_NOT_SUPPORTED', $this->shape_type);
        // Bounding Box
        $this->bounding_box = $this->readXYBoundingBox() + $this->readZRange() + $this->readMRange();
        if ($this->shape_type < 10 || $this->shape_type > 20) {
            unset($this->bounding_box['zmin']);
            unset($this->bounding_box['zmax']);
        }
        if ($this->shape_type < 10) {
            unset($this->bounding_box['mmin']);
            unset($this->bounding_box['mmax']);
        }
    }
    
    private function readDBFHeader()
    {
        $this->setFilePointer($this->dbf_handle, 4);
        if ($this->readInt32L($this->dbf_handle) !== $this->tot_records) $this->throwException('DBF_MISMATCHED_FILE');
        $this->dbf_header_size  = $this->readInt16L($this->dbf_handle);
        $this->dbf_record_size  = $this->readInt16L($this->dbf_handle);
        
        $i                  = -1;
        $this->dbf_fields   = array();
        $this->setFilePointer($this->dbf_handle, 32);
        while (ftell($this->dbf_handle) < $this->dbf_header_size - 1) {
            $i++;
            $this->dbf_fields[$i] = array(
                'name'  => $this->readString($this->dbf_handle, 11),
                'type'  => $this->readString($this->dbf_handle, 1)
            );
            $this->setFileOffset($this->dbf_handle, 4);
            $this->dbf_fields[$i] += array(
                'size'      => $this->readChar($this->dbf_handle),
                'decimals'  => $this->readChar($this->dbf_handle)
            );
            $this->setFileOffset($this->dbf_handle, 14);
        }
        // Field terminator
        if ($this->readChar($this->dbf_handle) !== 0x0d) $this->throwException('DBF_FILE_NOT_VALID');
    }
    
    
    private function readSHPRecord($geometry_format = self::GEOMETRY_BOTH)
    {
        if (!$this->valid()) return false;
        
        // Read SHP offset from SHX
        $this->setFilePointer($this->shx_handle, 100 + (($this->current_record - 1) * 8));
        $shp_offset = $this->readInt32B($this->shx_handle) * 2;
        $this->setFilePointer($this->shp_handle, $shp_offset);
        
        // Read SHP record header
        $record_number  = $this->readInt32B($this->shp_handle);
        $content_length = $this->readInt32B($this->shp_handle);
        $shape_type     = $this->readInt32L($this->shp_handle);
        if ($shape_type != 0 && $shape_type != $this->shape_type) $this->throwException('WRONG_RECORD_TYPE', $shape_type);
        
        // Read geometry
        $methods = array(
            0   => 'readNull',
            1   => 'readPoint',
            3   => 'readPolyLine',
            5   => 'readPolygon',
            8   => 'readMultiPoint',
            11  => 'readPointZ',
            13  => 'readPolyLineZ',
            15  => 'readPolygonZ',
            18  => 'readMultiPointZ',
            21  => 'readPointM',
            23  => 'readPolyLineM',
            25  => 'readPolygonM',
            28  => 'readMultiPointM'
        );
        $shp = $this->{$methods[$shape_type]}();
        if ($geometry_format == self::GEOMETRY_WKT)  $shp = $this->toWKT($shp);
        if ($geometry_format == self::GEOMETRY_BOTH) $shp['wkt'] = $this->toWKT($shp);
        
        return array(
            'shp'   => $shp,
            'dbf'   => $this->readDBFRecord()
        );
    }
    
    private function readDBFRecord()
    {
        $this->setFilePointer($this->dbf_handle, $this->dbf_header_size + (($this->current_record - 1) * $this->dbf_record_size));
        // Check if DBF is not corrupted (some "naive" users try to edit the DBF separately...)
        // Some GIS softwares don't include the last 0x1a byte in the DBF file, hence the "+ 1" in the following line
        if (ftell($this->dbf_handle) >= ($this->dbf_size - $this->dbf_record_size + 1)) $this->throwException('DBF_EOF_REACHED');
        
        $ret = array();
        $ret['_deleted'] = ($this->readChar($this->dbf_handle) !== 0x20);
        foreach ($this->dbf_fields as $i => $field) {
            $value = $this->readString($this->dbf_handle, $field['size']);
            switch ($field['type']) {
                case 'D':   // Date
                    $DateTime = \DateTime::createFromFormat('Ymd', $value);
                    if ($DateTime !== false) $value = $DateTime->format('Y-m-d');
                    break;
                case 'L':   // Logical
                    $value = in_array($value, array('Y', 'y', 'T', 't'));
                    break;
            }
            $ret[$field['name']] = $value;
        }
        
        return $ret;
    }
    
    private function checkRecordIndex($index)
    {
        return ($index > 0 && $index <= $this->tot_records);
    }
    
    
    
    private function readZ()
    {
        $ret    = array();
        $value  = $this->readDoubleL($this->shp_handle);
        if (!$this->flags[self::FLAG_SUPPRESS_Z]) $ret['z'] = $value;
        return $ret;
    }
    
    private function readM()
    {
        $ret    = array();
        $value  = $this->readDoubleL($this->shp_handle);
        if (!$this->flags[self::FLAG_SUPPRESS_M]) $ret['m'] = $this->parseM($value);
        return $ret;
    }
    
    private function parseM($value)
    {
        return ($value < -pow(10, 38)) ? false : $value;
    }
    
    
    private function readXYBoundingBox()
    {
        $xmin = $this->readDoubleL($this->shp_handle);
        $ymin = $this->readDoubleL($this->shp_handle);
        $xmax = $this->readDoubleL($this->shp_handle);
        $ymax = $this->readDoubleL($this->shp_handle);
        return array(
            'xmin'  => $xmin,
            'xmax'  => $xmax,
            'ymin'  => $ymin,
            'ymax'  => $ymax
        );
    }
    
    private function readZRange()
    {
        $values = array(
            'zmin'  => $this->readDoubleL($this->shp_handle),
            'zmax'  => $this->readDoubleL($this->shp_handle)
        );
        return $this->flags[self::FLAG_SUPPRESS_Z] ? array() : $values;
    }
    
    private function readMRange()
    {
        $values = array(
            'mmin'  => $this->parseM($this->readDoubleL($this->shp_handle)),
            'mmax'  => $this->parseM($this->readDoubleL($this->shp_handle))
        );
        return $this->flags[self::FLAG_SUPPRESS_M] ? array() : $values;
    }
    
    
    private function readNull()
    {
        return null;
    }
    
    
    private function readPoint()
    {
        return array(
            'x' => $this->readDoubleL($this->shp_handle),
            'y' => $this->readDoubleL($this->shp_handle)
        );
    }
    
    private function readPointM()
    {
        // Point
        $ret = $this->readPoint();
        // M Coord
        $ret += $this->readM();
        return $ret;
    }
    
    private function readPointZ()
    {
        // Point
        $ret = $this->readPoint();
        // Z Coord
        $ret += $this->readZ();
        // M Coord
        $ret += $this->readM();
        return $ret;
    }
    
    
    private function readMultiPoint()
    {
        // Header
        $ret = array(
            'bounding_box'  => $this->readXYBoundingBox(),
            'numpoints'     => $this->readInt32L($this->shp_handle),
            'points'        => array()
        );
        // Points
        for ($i=0; $i<$ret['numpoints']; $i++) {
            $ret['points'][] = $this->readPoint();
        }
        return $ret;
    }
    
    private function readMultiPointM()
    {
        // MultiPoint
        $ret = $this->readMultiPoint();
        // M Range
        $ret['bounding_box'] += $this->readMRange();
        // M Array
        for ($i=0; $i<$ret['numpoints']; $i++) {
            $ret['points'][$i] += $this->readM();
        }
        return $ret;
    }
    
    private function readMultiPointZ()
    {
        // MultiPoint
        $ret = $this->readMultiPoint();
        // Z Range
        $ret['bounding_box'] += $this->readZRange();
        // Z Array
        for ($i=0; $i<$ret['numpoints']; $i++) {
            $ret['points'][$i] += $this->readZ();
        }
        // M Range
        $ret['bounding_box'] += $this->readMRange();
        // M Array
        for ($i=0; $i<$ret['numpoints']; $i++) {
            $ret['points'][$i] += $this->readM();
        }
        return $ret;
    }
    
    
    private function readPolyLine()
    {
        // Header
        $ret = array(
            'bounding_box'  => $this->readXYBoundingBox(),
            'numparts'      => $this->readInt32L($this->shp_handle),
            'parts'         => array()
        );
        $tot_points = $this->readInt32L($this->shp_handle);
        // Parts
        $parts_first_index = array();
        for ($i=0; $i<$ret['numparts']; $i++) {
            $parts_first_index[$i]  = $this->readInt32L($this->shp_handle);
            $ret['parts'][$i]       = array(
                'numpoints' => 0,
                'points'    => array()
            );
        }
        // Points
        $part = 0;
        for ($i=0; $i<$tot_points; $i++) {
            if (isset($parts_first_index[$part + 1]) && $parts_first_index[$part + 1] == $i) $part++;
            $ret['parts'][$part]['points'][] = $this->readPoint();
        }
        for ($i=0; $i<$ret['numparts']; $i++) {
            $ret['parts'][$i]['numpoints'] = count($ret['parts'][$i]['points']);
        }
        return $ret;
    }
    
    private function readPolyLineM()
    {
        // PolyLine
        $ret = $this->readPolyLine();
        // M Range
        $ret['bounding_box'] += $this->readMRange();
        // M Array
        for ($i=0; $i<$ret['numparts']; $i++) {
            for ($k=0; $k<$ret['parts'][$i]['numpoints']; $k++) {
                $ret['parts'][$i]['points'][$k] += $this->readM();
            }
        }
        return $ret;
    }
    
    private function readPolyLineZ()
    {
        // PolyLine
        $ret = $this->readPolyLine();
        // Z Range
        $ret['bounding_box'] += $this->readZRange();
        // Z Array
        for ($i=0; $i<$ret['numparts']; $i++) {
            for ($k=0; $k<$ret['parts'][$i]['numpoints']; $k++) {
                $ret['parts'][$i]['points'][$k] += $this->readZ();
            }
        }
        // M Range
        $ret['bounding_box'] += $this->readMRange();
        // M Array
        for ($i=0; $i<$ret['numparts']; $i++) {
            for ($k=0; $k<$ret['parts'][$i]['numpoints']; $k++) {
                $ret['parts'][$i]['points'][$k] += $this->readM();
            }
        }
        return $ret;
    }
    
    
    private function readPolygon()
    {
        return $this->parsePolygon($this->readPolyLine());
    }
    
    private function readPolygonM()
    {
        return $this->parsePolygon($this->readPolyLineM());
    }
    
    private function readPolygonZ()
    {
        return $this->parsePolygon($this->readPolyLineZ());
    }
    
    
    private function parsePolygon($data)
    {
        $i      = -1;
        $parts  = array();
        foreach ($data['parts'] as $rawpart) {
            if ($this->isClockwise($rawpart['points'])) {
                $i++;
                $parts[$i] = array(
                    'numrings'  => 0,
                    'rings'     => array()
                );
            }
            if ($i < 0) $this->throwException('POLYGON_NOT_VALID');
            $parts[$i]['rings'][] = $rawpart;
        }
        for ($i=0; $i<count($parts); $i++) {
            $parts[$i]['numrings'] = count($parts[$i]['rings']);
        }
        return array(
            'bounding_box'  => $data['bounding_box'],
            'numparts'      => count($parts),
            'parts'         => $parts
        );
    }
    
    private function isClockwise($points, $exp = 1)
    {
        $num_points = count($points);
        if ($num_points < 2) return true;
        
        $num_points--;
        $tot = 0;
        for ($i=0; $i<$num_points; $i++) {
            $tot += ($exp * $points[$i]['x'] * $points[$i+1]['y']) - ($exp * $points[$i]['y'] * $points[$i+1]['x']);
        }
        $tot += ($exp * $points[$num_points]['x'] * $points[0]['y']) - ($exp * $points[$num_points]['y'] * $points[0]['x']);
        
        if ($tot == 0) {
            if ($exp >= pow(10, 9)) $this->throwException('POLYGON_AREA_TOO_SMALL');
            return $this->isClockwise($points, $exp * pow(10, 3));
        }
        
        return $tot < 0;
    }
    
    
    private function toWKT($data)
    {
        if (!$data) return null;
        $geom_type  = $this->shape_type % 10;
        $coord_type = floor($this->shape_type / 10);
        $z          = !$this->flags[self::FLAG_SUPPRESS_Z] && ($coord_type == 1);
        $ret        = null;
        switch ($geom_type) {
            case 1:
                $m   = (!$this->flags[self::FLAG_SUPPRESS_M] && $coord_type > 0) ? $this->checkPointsM(array($data)) : false;
                $ret = 'POINT'.($z ? 'Z' : '').($m ? 'M' : '').$this->implodePoints(array($data), $z, $m);
                break;
            
            case 8:
                $m   = (!$this->flags[self::FLAG_SUPPRESS_M] && $coord_type > 0) ? $this->checkPointsM($data['points']) : false;
                $ret = 'MULTIPOINT'.($z ? 'Z' : '').($m ? 'M' : '').$this->implodePoints($data['points'], $z, $m);
                break;
            
            case 3:
                $m = (!$this->flags[self::FLAG_SUPPRESS_M] && $coord_type > 0) ? $this->checkPartsM($data['parts']) : false;
                if ($data['numparts'] == 1) {
                    $ret = 'LINESTRING'.($z ? 'Z' : '').($m ? 'M' : '').$this->implodeParts($data['parts'], $z, $m);
                } else {
                    $ret = 'MULTILINESTRING'.($z ? 'Z' : '').($m ? 'M' : '').'('.$this->implodeParts($data['parts'], $z, $m).')';
                }
                break;
            
            case 5:
                $m = false;
                if (!$this->flags[self::FLAG_SUPPRESS_M] && $coord_type > 0) {
                    foreach ($data['parts'] as $part) {
                        if ($this->checkPartsM($part['rings'])) {
                            $m = true;
                            break;
                        }
                    }
                }
                $wkt = array();
                foreach ($data['parts'] as $part) {
                    $wkt[] = '('.$this->implodeParts($part['rings'], $z, $m).')';
                }
                if ($data['numparts'] == 1) {
                    $ret = 'POLYGON'.($z ? 'Z' : '').($m ? 'M' : '').implode(', ', $wkt);
                } else {
                    $ret = 'MULTIPOLYGON'.($z ? 'Z' : '').($m ? 'M' : '').'('.implode(', ', $wkt).')';
                }
                break;
        }
        return $ret;
    }
    
    private function checkPartsM($parts)
    {
        foreach ($parts as $part) {
            if ($this->checkPointsM($part['points'])) return true;
        }
        return false;
    }
    
    private function checkPointsM($points)
    {
        foreach ($points as $point) {
            if ($point['m'] !== false) return true;
        }
        return false;
    }
    
    private function implodeParts($parts, $flagZ, $flagM)
    {
        $wkt = array();
        foreach ($parts as $part) {
            $wkt[] = $this->implodePoints($part['points'], $flagZ, $flagM);
        }
        return implode(', ', $wkt);
    }
    
    private function implodePoints($points, $flagZ, $flagM)
    {
        $wkt = array();
        foreach ($points as $point) {
            $wkt[] = $point['x'].' '.$point['y'].($flagZ ? ' '.$point['z'] : '').($flagM ? ' '.($point['m'] === false ? '0' : $point['m']) : '');
        }
        return '('.implode(', ', $wkt).')';
    }
    
    
    private function throwException($error, $details = '')
    {
        $code       = self::$error_messages[$error][0];
        $message    = self::$error_messages[$error][1];
        if ($details != '') $message .= ': "'.$details.'"';
        throw new ShapeFileException($message, $code, $error);
    }
}
