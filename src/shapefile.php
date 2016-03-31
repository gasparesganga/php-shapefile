<?php
/***************************************************************************************************
ShapeFile - PHP Class to read an ESRI Shapefile and its associated DBF
    Author          : Gaspare Sganga
    Version         : 1.1
    License         : MIT
    Documentation   : http://gasparesganga.com/labs/php-shapefile
****************************************************************************************************/

// =================================================================================================
// Subsitute for PHP dBase functions
if (!function_exists('dbase_open')) require_once(dirname(__FILE__).'/dbase_functions.php');
// =================================================================================================

class ShapeFile {
    
    // getShapeType() return type
    const FORMAT_INT        = 0;
    const FORMAT_STR        = 1;
    // getRecord() Geometry format
    const GEOMETRY_ARRAY    = 0;
    const GEOMETRY_WKT      = 1;
    
    private static $error_messages = array(
        'FILE_EXISTS'               => array(11, "File not found. Check if the file exists and is readable"),
        'FILE_OPEN'                 => array(12, "Unable to read file"),
        'SHAPE_TYPE_NOT_SUPPORTED'  => array(21, "Shape Type not supported"),
        'WRONG_RECORD_TYPE'         => array(22, "Wrong Record's Shape Type"),
        'POLYGON_AREA_TOO_SMALL'    => array(31, "Polygon Area too small, can't determine vertex orientation"),
        'POLYGON_NOT_VALID'         => array(32, "Polygon not valid or Polygon Area too small. Please check the geometries before reading the Shapefile")
    );
    private static $binary_data_lengths = array(
        'd' => 8,
        'V' => 4,
        'N' => 4
    );
    private static $shape_types = array( 
        0   => 'Null',
        1   => 'Point',
        8   => 'MultiPoint',
        3   => 'PolyLine',
        5   => 'Polygon'
    );
    
    private $shp_handle;
    private $dbf_handle;
    private $shp_file_size;
    private $shape_type;
    private $bounding_box;
    private $prj_file;
    
    
    public function __construct($files)
    {
        if (is_string($files)) {
            $basename = (substr($files, -4) == '.shp') ? substr($files, 0, -4) : $files;
            $shp_file = $basename.'.shp';
            $dbf_file = $basename.'.dbf';
            $prj_file = $basename.'.prj'; 
        } else {
            $shp_file = isset($files['shp']) ? $files['shp'] : ''; 
            $dbf_file = isset($files['dbf']) ? $files['dbf'] : ''; 
            $prj_file = isset($files['prj']) ? $files['prj'] : ''; 
        }
        
        if (!(is_readable($shp_file) && is_file($shp_file))) $this->Error('FILE_EXISTS', '(SHP) '.$shp_file);
        $this->shp_handle = fopen($shp_file, 'rb');
        if (!$this->shp_handle) $this->Error('FILE_OPEN', '(SHP) '.$shp_file);
        
        if (!(is_readable($dbf_file) && is_file($dbf_file))) $this->Error('FILE_EXISTS', '(DBF) '.$dbf_file);
        $this->dbf_handle = dbase_open($dbf_file, 0);
        if ($this->dbf_handle === false) $this->Error('FILE_OPEN', '(DBF) '.$dbf_file);
        
        $this->prj_file = $prj_file;
        
        $this->shp_file_size = filesize($shp_file);
        $this->LoadHeader();
    }
    
    public function __destruct()
    {
        if ($this->shp_handle) fclose($this->shp_handle);
        if ($this->dbf_handle) dbase_close($this->dbf_handle);
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
        if (is_readable($this->prj_file) && is_file($this->prj_file)) return file_get_contents($this->prj_file);
        return null;
    }
    
    
    public function getRecord($geometry_format = self::GEOMETRY_ARRAY)
    {
        if (ftell($this->shp_handle) >= $this->shp_file_size) return false;
        $record_number  = $this->ReadData('N');
        $content_length = $this->ReadData('N');
        $shape_type     = $this->ReadData('V');
        if ($shape_type != 0 && $shape_type != $this->shape_type) $this->Error('WRONG_RECORD_TYPE', $shape_type);
        switch ($shape_type) {
            case 0:
                $shp = null;
                break;
            case 1:
                $shp = $this->ReadPoint();
                break;
            case 8:
                $shp = $this->ReadMultiPoint();
                break;
            case 3:
                $shp = $this->ReadPolyLine();
                break;
            case 5:
                $shp = $this->ReadPolygon();
                break;
        }
        if ($geometry_format == self::GEOMETRY_WKT) $shp = $this->WKT($shp);
        return array(
            'shp'   => $shp,
            'dbf'   => dbase_get_record_with_names($this->dbf_handle, $record_number)
        );
    }
    
    
    /******************** PRIVATE ********************/
    private function ReadData($type)
    {
        $data = fread($this->shp_handle, self::$binary_data_lengths[$type]);
        if (!$data) return null;
        return current(unpack($type, $data));
    }
    
    private function ReadBoundingBox()
    {
        return array(
            'xmin'  => $this->ReadData('d'),
            'ymin'  => $this->ReadData('d'),
            'xmax'  => $this->ReadData('d'),
            'ymax'  => $this->ReadData('d')
        );
    }
    
    private function LoadHeader()
    {
        fseek($this->shp_handle, 32, SEEK_SET);
        $this->shape_type = $this->ReadData('V');
        if (!isset(self::$shape_types[$this->shape_type])) $this->Error('SHAPE_TYPE_NOT_SUPPORTED', $this->shape_type);
        $this->bounding_box = $this->ReadBoundingBox();
        fseek($this->shp_handle, 100, SEEK_SET);
    }
    
    
    private function ReadPoint()
    {
        return array(
            'x' => $this->ReadData('d'),
            'y' => $this->ReadData('d')
        );
    }
    
    private function ReadMultiPoint()
    {
        // Header
        $ret = array(
            'bounding_box'  => $this->ReadBoundingBox(),
            'numpoints'     => $this->ReadData('V'),
            'points'        => array()
        );
        // Points
        for ($i=0; $i<$ret['numpoints']; $i++) {
            $ret['points'][] = $this->ReadPoint();
        }
        return $ret;
    }
    
    private function ReadPolyLine()
    {
        // Header
        $ret = array(
            'bounding_box'  => $this->ReadBoundingBox(),
            'numparts'      => $this->ReadData('V'),
            'parts'         => array()
        );
        $tot_points = $this->ReadData('V');
        // Parts
        $parts_first_index = array();
        for ($i=0; $i<$ret['numparts']; $i++) {
            $parts_first_index[$i]  = $this->ReadData('V');
            $ret['parts'][$i]       = array(
                'numpoints' => 0,
                'points'    => array()
            );
        }
        // Points
        $part = 0;
        for ($i=0; $i<$tot_points; $i++) {
            if (isset($parts_first_index[$part + 1]) && $parts_first_index[$part + 1] == $i) $part++;
            $ret['parts'][$part]['points'][] = $this->ReadPoint();
        }
        for ($i=0; $i<$ret['numparts']; $i++) {
            $ret['parts'][$i]['numpoints'] = count($ret['parts'][$i]['points']);
        }
        return $ret;
    }
    
    private function ReadPolygon()
    {
        // Read as Polyline
        $data = $this->ReadPolyLine();
        // Rings
        $i      = -1;
        $parts  = array();
        foreach ($data['parts'] as $rawpart) {
            if ($this->IsClockwise($rawpart['points'])) {
                $i++;
                $parts[$i] = array(
                    'numrings'  => 0,
                    'rings'     => array()
                );
            }
            if ($i < 0) $this->Error('POLYGON_NOT_VALID');
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
    
    private function IsClockwise($points, $exp = 1)
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
            if ($exp >= 1000000000) $this->Error('POLYGON_AREA_TOO_SMALL');
            return $this->IsClockwise($points, $exp * 1000);
        }
        
        return $tot < 0; 
    }
    
    
    private function WKT($data)
    {
        if (!$data) return null;
        switch ($this->shape_type) {
            case 1:
                return 'POINT('.$data['x'].' '.$data['y'].')';
            
            case 8:
                return 'MULTIPOINT'.$this->ImplodePoints($data['points']);
            
            case 3:
                if ($data['numparts'] > 1) {
                    return 'MULTILINESTRING('.$this->ImplodeParts($data['parts']).')';
                } else {
                    return 'LINESTRING'.$this->ImplodeParts($data['parts']);
                }
            
            case 5:
                $wkt = array();
                foreach ($data['parts'] as $part) {
                    $wkt[] = '('.$this->ImplodeParts($part['rings']).')';
                }
                if ($data['numparts'] > 1) {
                    return 'MULTIPOLYGON('.implode(', ', $wkt).')';
                } else {
                    return 'POLYGON'.implode(', ', $wkt);
                }
        }
    }
    
    private function ImplodeParts($parts)
    {
        $wkt = array();
        foreach ($parts as $part) {
            $wkt[] = $this->ImplodePoints($part['points']);
        }
        return implode(', ', $wkt);
    }
    
    private function ImplodePoints($points)
    {
        $wkt = array();
        foreach ($points as $point) {
            $wkt[] = $point['x'].' '.$point['y'];
        }
        return '('.implode(', ', $wkt).')';
    }
    
    
    private function Error($error, $details = '')
    {
        $code       = self::$error_messages[$error][0];
        $message    = self::$error_messages[$error][1];
        if ($details != '') $message .= ': "'.$details.'"';
        throw new ShapeFileException($message, $code);
    }
    
}

class ShapeFileException extends Exception {}
?>