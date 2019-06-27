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

class ShapefileWriter extends AbstractShapefile
{
    // Geometries
    private $geometries  = [];
    
    
    
    // DBF
    private $dbf_charset;
    
    // Misc
    private $flag_init = false;
    
    
    /////////////////////////////// PUBLIC ///////////////////////////////
    public function __construct($files, $options = array())
    {
        
        /*if (is_string($files)) {
            $basename = (substr($files, -4) == '.shp') ? substr($files, 0, -4) : $files;
            $shp_file = $basename.'.shp';
            $shx_file = $basename.'.shx';
            $dbf_file = $basename.'.dbf';
            $prj_file = $basename.'.prj';
            $cpg_file = $basename.'.cpg';
            $cst_file = $basename.'.cst';
        } else {
            $shp_file = isset($files['shp']) ? $files['shp'] : '';
            $shx_file = isset($files['shx']) ? $files['shx'] : '';
            $dbf_file = isset($files['dbf']) ? $files['dbf'] : '';
            $prj_file = isset($files['prj']) ? $files['prj'] : '';
            $cpg_file = isset($files['cpg']) ? $files['cpg'] : '';
            $cst_file = isset($files['cst']) ? $files['cst'] : '';
        }
        
        $this->init(
            // SHP : Handle & Filesize
            $this->openFile($shp_file),
            filesize($shp_file),
            // SHX : Handle & Filesize
            $this->openFile($shx_file),
            filesize($shx_file),
            // DBF : Handle & Filesize
            $this->openFile($dbf_file),
            filesize($dbf_file),
            // PRJ : File contents as string
            (is_readable($prj_file) && is_file($prj_file)) ? file_get_contents($prj_file) : null,
            // CPG : File contents as string
            (is_readable($cpg_file) && is_file($cpg_file)) ? file_get_contents($cpg_file) : null,
            // CST : File contents as string
            (is_readable($cst_file) && is_file($cst_file)) ? file_get_contents($cst_file) : null,
            // Options
            $options
        );*/
    }
    
    public function __destruct()
    {
        /*$this->closeFile($this->shp_handle);
        $this->closeFile($this->shx_handle);
        $this->closeFile($this->dbf_handle);*/
    }
    
    
    
    
    
     /*
        QUESTA É LA CONVERSIONE SOLO PER ShapefileWriter !!!
    */
    public function parseValue($field_name, $value)
    {
        $this->checkField($field_name);
        switch ($this->fields[$field_name]['type']) {
            case Shapefile::DBF_TYPE_CHAR:
                $value = substr($value, 0, $this->fields[$field_name]['size']);
                break;
            
            case Shapefile::DBF_TYPE_DATE:
                // verifica se è un DateTime, se è ISO YYYYMMDD, YYYY-MM-DD
                /*$DateTime   = \DateTime::createFromFormat('Ymd', $value);
                $errors     = \DateTime::getLastErrors();
                if ($errors['warning_count'] || $errors['error_count']) {
                    $value = $this->options[Shapefile::OPTION_NULLIFY_INVALID_DATES] ? null : $value;
                } else {
                    $value = $DateTime->format('Y-m-d');
                }  
                break;*/
                
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
                echo "Fai qualcosa coi campi MEMO!";
                break;
            
            case Shapefile::DBF_TYPE_NUMERIC:
                $value = trim($value);
                $decimals = $this->fields[$field_name]['decimals'];
                $flag_negative = substr($value, 0, 1) == '-';
                $intpart = $this->sanitizeNumber(strpos($value, '.') === false ? $value : strstr($value, '.', true));
                $decpart = $this->sanitizeNumber(substr(strstr($value, '.', false), 1));
                $decpart = strlen($decpart) > $decimals ? substr($decpart, 0, $decimals) : str_pad($decpart, $decimals, '0', STR_PAD_RIGHT);
                $value = ($flag_negative ? '-' : '') . $intpart . ($decimals > 0 ? '.' : '') . $decpart;
                
                //if () { SE è troppo grande!
                    
                // qui bisogna fare controlli su size!
                
                
            break;
            
        }
        
        return $value;
        
        //
        // VERIFICA FORMATO VALORE
        //throw new ShapefileException(Shapefile::ERR_INPUT_VALUE_NOT_VALID, $value);
        //questa eccezione ERR_INPUT_VALUE_NOT_VALID ancora non esiste!!!
    }
    
    private function sanitizeNumber($value)
    {
        return preg_replace('/[^0-9]/', '', $value);
    }
    
    
    /**
     * Encodes a value to be written into a DBF field as a raw string.
     *
     * @param   string  $field      Name of the field.
     * @param   string  $value      Value to encode.
     *
     * @return  string
     */
    private function encodeFieldValue($field, $value)
    {
        // TO BE IMPLEMENTED!
    }
    
    
    
    
    
    
    
    
    
    
    
    
    

}
