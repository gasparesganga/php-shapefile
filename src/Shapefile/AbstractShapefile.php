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

/**
 * Abstract base class for ShapefileReader and ShapefileWriter.
 * It provides some common public methods to both of them.
 */
abstract class AbstractShapefile
{
    /**
     * @var integer|null    Shapefile type.
     */
    private $shape_type = null;
    
    /**
     * @var array|null      Custom bounding box set with setCustomBoundingBox() method.
     */
    private $custom_bounding_box = null;
    
    /**
     * @var array|null      Computed bounding box.
     */
    private $computed_bounding_box = null;
    
    
    /**
     * @var string|null     PRJ well-known-text.
     */
    private $prj = null;
    
    
    /**
     * @var string|null     DBF charset.
     */
    private $dbf_charset = null;
    
    /**
     * @var array   Fields definition.
     *              Every field is represented by an array with the following structure:
     *              [
     *                  "type"      => string
     *                  "size"      => integer
     *                  "decimals"  => integer
     *              ]
     */
    private $fields = [];
    
    
    /**
     * @var bool    Flag representing whether the Shapefile has been initialized with any Geometry.
     */
    private $flag_init = false;
    
    
    /**
     * @var array   Constructor options.
     */
    private $options = [];
    
    
    
    /////////////////////////////// PUBLIC ///////////////////////////////
    /**
     * Pair a Geometry with the Shapefile.
     * It enforces the Geometry type, compute Shapefile bounding box and call Geometry setShapefile() method.
     * After that the Shapefile will be considered as "initialized" and no changes will be allowd to its structure.
     * 
     * @param   AbstractGeometry    $Geometry     Geometry to add.
     */
    public function addGeometry(Geometry\AbstractGeometry $Geometry)
    {
        // Geometry type
        $this->checkShapeType();
        if (
            $Geometry->getSHPBasetype() !== $this->getBasetype()            ||
            (!$Geometry->isEmpty() && ($Geometry->isZ() !== $this->isZ()))  ||
            (!$Geometry->isEmpty() && ($Geometry->isM() !== $this->isM()))
        ) {
            throw new ShapefileException(Shapefile::ERR_SHP_GEOMETRY_TYPE_NOT_COMPATIBLE, $this->getShapeType(Shapefile::FORMAT_INT) . ' - ' . $this->getShapeType(Shapefile::FORMAT_STR));
        }
        
        // Bounding box
        $bbox = $Geometry->getBoundingBox();
        if (!$this->computed_bounding_box && $bbox) {
            $this->computed_bounding_box = $bbox;
        } elseif ($bbox) {
            $this->computed_bounding_box['xmin'] = $bbox['xmin'] < $this->computed_bounding_box['xmin'] ? $bbox['xmin'] : $this->computed_bounding_box['xmin'];
            $this->computed_bounding_box['xmax'] = $bbox['xmax'] > $this->computed_bounding_box['xmax'] ? $bbox['xmax'] : $this->computed_bounding_box['xmax'];
            $this->computed_bounding_box['ymin'] = $bbox['ymin'] < $this->computed_bounding_box['ymin'] ? $bbox['ymin'] : $this->computed_bounding_box['ymin'];
            $this->computed_bounding_box['ymax'] = $bbox['ymax'] > $this->computed_bounding_box['ymax'] ? $bbox['ymax'] : $this->computed_bounding_box['ymax'];
            if ($this->isZ()) {
                $this->computed_bounding_box['zmin'] = $bbox['zmin'] < $this->computed_bounding_box['zmin'] ? $bbox['zmin'] : $this->computed_bounding_box['zmin'];
                $this->computed_bounding_box['zmax'] = $bbox['zmax'] > $this->computed_bounding_box['zmax'] ? $bbox['zmax'] : $this->computed_bounding_box['zmax'];
            }
            if ($this->isM()) {
                $this->computed_bounding_box['mmin'] = ($this->computed_bounding_box['mmin'] === false || $bbox['mmin'] < $this->computed_bounding_box['mmin']) ? $bbox['mmin'] : $this->computed_bounding_box['mmin'];
                $this->computed_bounding_box['mmax'] = ($this->computed_bounding_box['mmax'] === false || $bbox['mmax'] > $this->computed_bounding_box['mmax']) ? $bbox['mmax'] : $this->computed_bounding_box['mmax'];
            }
        }
        // Init Geometry with fields and flag Shapefile as initialized
        $Geometry->setShapefile($this);
        $this->flag_init = true;
    }
    
    
    /**
     * Gets shape type either as integer or string.
     * 
     * @param   integer $format     Optional desired output format.
     *                              It can be on of the following:
     *                              - Shapefile::FORMAT_INT [default]
     *                              - Shapefile::FORMAT_STR
     * 
     * @return  integer|string
     */
    public function getShapeType($format = Shapefile::FORMAT_INT)
    {
        if ($this->shape_type === null) {
            return null;
        }
        if ($format == Shapefile::FORMAT_STR) {
            return Shapefile::$shape_types[$this->shape_type];
        } else {
            return $this->shape_type;
        }
    }
    
    /**
     * Sets shape type.
     * It can be called just once for an instance of the class.
     * 
     * @param   integer $type   Shape type. It can be on of the following:
     *                          - Shapefile::SHAPE_TYPE_NULL
     *                          - Shapefile::SHAPE_TYPE_POINT
     *                          - Shapefile::SHAPE_TYPE_POLYLINE
     *                          - Shapefile::SHAPE_TYPE_POLYGON
     *                          - Shapefile::SHAPE_TYPE_MULTIPOINT
     *                          - Shapefile::SHAPE_TYPE_POINTZ
     *                          - Shapefile::SHAPE_TYPE_POLYLINEZ
     *                          - Shapefile::SHAPE_TYPE_POLYGONZ
     *                          - Shapefile::SHAPE_TYPE_MULTIPOINTZ
     *                          - Shapefile::SHAPE_TYPE_POINTM
     *                          - Shapefile::SHAPE_TYPE_POLYLINEM
     *                          - Shapefile::SHAPE_TYPE_POLYGONM
     *                          - Shapefile::SHAPE_TYPE_MULTIPOINTM
     */
    public function setShapeType($type)
    {
        if ($this->shape_type !== null) {
            throw new ShapefileException(Shapefile::ERR_SHP_TYPE_ALREADY_SET);
        }
        if (!isset(Shapefile::$shape_types[$type])) {
            throw new ShapefileException(Shapefile::ERR_SHP_TYPE_NOT_SUPPORTED, $type);
        }
        $this->shape_type = $type;
    }
    
    
    /**
     * Gets Shapefile bounding box.
     * 
     * @return  array
     */
    public function getBoundingBox()
    {
        return $this->custom_bounding_box ?: $this->computed_bounding_box;
    }
    
    /**
     * Sets a custom bounding box for the Shapefile.
     * No formal check is carried out except the compliance of dimensions.
     *
     * @param   array   $bounding_box    Associative array with the xmin, xmax, ymin, ymax and optional zmin, zmax, mmin, mmax values.
     */
    public function setCustomBoundingBox($bounding_box)
    {
        if (
            !isset($bounding_box['xmin'], $bounding_box['xmax'], $bounding_box['ymin'], $bounding_box['ymax'])  ||
            ($this->isZ() && !isset($bounding_box['zmin'], $bounding_box['zmax']))                              ||
            ($this->isM() && !isset($bounding_box['mmin'], $bounding_box['mmax']))
        ) {
            throw new ShapefileException(Shapefile::ERR_SHP_MISMATCHED_BBOX);
        }
        $this->custom_bounding_box = $bounding_box;
    }
    
    /**
     * Resets custom bounding box for the Shapefile.
     * It will cause getBoundingBox() method to return a normally computed bbox instead of a custom one.
     */
    public function resetCustomBoundingBox()
    {
        $this->custom_bounding_box = null;
    }
    
    
    /**
     * Gets PRJ well-known-text.
     *
     * @return  string
     */
    public function getPRJ()
    {
        return $this->prj;
    }
    
    /**
     * Sets PRJ well-known-text.
     *
     * @param   string  $prj    PRJ well-known-text.
     *                          Pass a falsy value (ie. false or "") to delete it.
     */
    public function setPRJ($prj)
    {
        $this->prj = $prj ?: null;
    }
    
    
    /**
     * Gets DBF charset.
     *
     * @return  string
     */
    public function getDBFCharset()
    {
        return $this->dbf_charset;
    }
    
    /**
     * Sets or resets DBF charset.
     *
     * @param   string  $charset    Name of the charset.
     *                              Pass a falsy value (ie. false or "") to reset it to default.
     */
    public function setDBFCharset($charset)
    {
        $this->dbf_charset = $charset ?: Shapefile::DEFAULT_DBF_CHARSET;
    }
    
    
    /**
     * Adds a char field to the Shapefile definition.
     * 
     * @param   string  $name               Name of the field. Maximum 10 characters.
     *                                      Only letters, numbers and underscores are allowed.
     * @param   integer $size               Lenght of the field, between 1 and 254 characters.
     * @param   bool    $flag_sanitize_name Optional flag to automatically replace illegal characters
     *                                      in the name with underscores. Defaults to true.
     */
    public function addCharField($name, $size, $flag_sanitize_name = true)
    {
        $this->addField($name, Shapefile::DBF_TYPE_CHAR, $size);
    }
    
    /**
     * Adds a date field to the Shapefile definition.
     * 
     * @param   string  $name               Name of the field. Maximum 10 characters.
     *                                      Only letters, numbers and underscores are allowed.
     * @param   bool    $flag_sanitize_name Optional flag to automatically replace illegal characters
     *                                      in the name with underscores. Defaults to true.
     */
    public function addDateField($name, $flag_sanitize_name = true)
    {
        $this->addField($name, Shapefile::DBF_TYPE_DATE, 8);
    }
    
    /**
     * Adds a logical/boolean field to the Shapefile definition.
     * 
     * @param   string  $name               Name of the field. Maximum 10 characters.
     *                                      Only letters, numbers and underscores are allowed.
     * @param   bool    $flag_sanitize_name Optional flag to automatically replace illegal characters
     *                                      in the name with underscores. Defaults to true.
     */
    public function addLogicalField($name, $flag_sanitize_name = true)
    {
        $this->addField($name, Shapefile::DBF_TYPE_LOGICAL, 1);
    }
    
    /**
     * Adds a memo field to the Shapefile definition.
     * 
     * @param   string  $name               Name of the field. Maximum 10 characters.
     *                                      Only letters, numbers and underscores are allowed.
     * @param   bool    $flag_sanitize_name Optional flag to automatically replace illegal characters
     *                                      in the name with underscores. Defaults to true.
     */
    public function addMemoField($name, $flag_sanitize_name = true)
    {
        $this->addField($name, Shapefile::DBF_TYPE_MEMO, 10);
    }
    
    /**
     * Adds numeric to the Shapefile definition.
     * 
     * @param   string  $name               Name of the field. Maximum 10 characters.
     *                                      Only letters, numbers and underscores are allowed.
     * @param   integer $size               Lenght of the field, between 1 and 254 characters.
     * @param   integer $decimals           Optional number of decimal digits for numeric type.
     * @param   bool    $flag_sanitize_name Optional flag to automatically replace illegal characters
     *                                      in the name with underscores. Defaults to true.
     */
    public function addNumericField($name, $size, $decimals = 0, $flag_sanitize_name = true)
    {
        $this->addField($name, Shapefile::DBF_TYPE_NUMERIC, $size, $decimals);
    }
    
    /**
     * Adds field to the shapefile definition.
     * Returns the actual field name after eventual sanitization.
     * 
     * @param   string  $name               Name of the field. Maximum 10 characters.
     *                                      Only letters, numbers and underscores are allowed.
     * @param   string  $type               Type of the field. It can be on of the following:
     *                                      - Shapefile::DBF_TYPE_CHAR
     *                                      - Shapefile::DBF_TYPE_DATE
     *                                      - Shapefile::DBF_TYPE_LOGICAL
     *                                      - Shapefile::DBF_TYPE_MEMO
     *                                      - Shapefile::DBF_TYPE_NUMERIC
     * @param   integer $size               Lenght of the field, depending on the type.
     * @param   integer $decimals           Optional number of decimal digits for numeric type.
     * @param   bool    $flag_sanitize_name Optional flag to automatically replace illegal characters
     *                                      in the name with underscores. Defaults to true.
     *
     * @return  string
     */
    public function addField($name, $type, $size, $decimals = 0, $flag_sanitize_name = true)
    {
        $this->checkInit();
        if (count($this->fields) >= Shapefile::DBF_MAX_FIELD_COUNT) {
            throw new ShapefileException(Shapefile::ERR_DBF_MAX_FIELD_COUNT_REACHED, Shapefile::DBF_MAX_FIELD_COUNT);
        }
        
        // Sanitize name
        $sanitized_name = $this->sanitizeDBFFieldName($name);
        if ($flag_sanitize_name) {
            $name = $sanitized_name;
        } elseif ($name !== $sanitized_name) {
            throw new ShapefileException(Shapefile::ERR_DBF_FIELD_NAME_NOT_VALID, $name);
        }
        
        // Check if name already exists
        if (array_key_exists(strtoupper($name), array_change_key_case($this->fields, CASE_UPPER))) {
            throw new ShapefileException(Shapefile::ERR_DBF_FIELD_NAME_NOT_UNIQUE, $name);
        }
        
        // Check type
        if (
            $type !== Shapefile::DBF_TYPE_CHAR      &&
            $type !== Shapefile::DBF_TYPE_DATE      &&
            $type !== Shapefile::DBF_TYPE_LOGICAL   &&
            $type !== Shapefile::DBF_TYPE_MEMO      &&
            $type !== Shapefile::DBF_TYPE_NUMERIC
        ) {
            throw new ShapefileException(Shapefile::ERR_DBF_FIELD_TYPE_NOT_VALID, $type);
        }
        
        // Check size
        $size = intval($size);
        if (
            ($size < 1)                                            ||
            ($type == Shapefile::DBF_TYPE_CHAR && $size > 254)     ||
            ($type == Shapefile::DBF_TYPE_DATE && $size !== 8)     ||
            ($type == Shapefile::DBF_TYPE_LOGICAL && $size !== 1)  ||
            ($type == Shapefile::DBF_TYPE_MEMO && $size !== 10)    ||
            ($type == Shapefile::DBF_TYPE_NUMERIC && $size > 254)
        ) {
            throw new ShapefileException(Shapefile::ERR_DBF_FIELD_SIZE_NOT_VALID, $size);
        }
        
        // Minimal decimal formal check
        $decimals = intval($decimals);
        if (
            ($type != Shapefile::DBF_TYPE_NUMERIC && $decimals !== 0)   ||
            ($decimals < 0)                                             ||
            ($decimals > 0 && $decimals > $size - 2)
        ) {
            throw new ShapefileException(Shapefile::ERR_DBF_FIELD_DECIMALS_NOT_VALID, $decimals);
        }
        
        // Add field
        $this->fields[$name] = [
            'type'      => $type,
            'size'      => $size,
            'decimals'  => $decimals,
        ];
        
        return $name;
    }
    
    
    /**
     * Gets a complete field definition.
     * 
     * The returned array contains the following elements:
     *  [
     *      "type"      => string
     *      "size"      => integer
     *      "decimals"  => integer
     *  ]
     *
     * @param   string  $name   Name of the field.
     *
     * @return  array
     */
    public function getField($name)
    {
        $this->checkField($name);
        return $this->fields[$name];
    }
    
    /**
     * Gets a field type.
     *
     * @param   string  $name   Name of the field.
     *
     * @return  string
     */
    public function getFieldType($name)
    {
        return $this->getField($name)['type'];
    }
    
    /**
     * Gets a field size.
     *
     * @param   string  $name   Name of the field.
     *
     * @return  integer
     */
    public function getFieldSize($name)
    {
        return $this->getField($name)['size'];
    }
    
    /**
     * Gets a field decimals.
     *
     * @param   string  $name   Name of the field.
     *
     * @return  integer
     */
    public function getFieldDecimals($name)
    {
        return $this->getField($name)['decimals'];
    }
    
    /**
     * Gets all fields definition.
     * 
     * @return  array
     */
    public function getFields()
    {
        return $this->fields;
    }
    
    
    /**
     * Checks if field is defined and if not throws and exception.
     * This is not intended for users, but this class and AbstractGeometry require it for internal mechanisms.
     *
     * @internal
     *
     * @param   string  $name       Name of the field.
     */
    public function checkField($name, $flag_sanitize_name = true)
    {
        if (!isset($this->fields[$name])) {
            throw new ShapefileException(Shapefile::ERR_INPUT_FIELD_NOT_FOUND, $name);
        }
    }
    
    
    
    /////////////////////////////// PROTECTED ///////////////////////////////
    /**
     * Initializes options with default and user-provided values.
     * 
     * @param   array   $options    Array of options to initialize.
     * @param   array   $custom     User-provided options
     */
    protected function initOptions($options, $custom)
    {
        // Defaults
        $defaults = [];
        foreach ($options as $option) {
            $defaults[$option] = constant('Shapefile\Shapefile::' . $option . '_DEFAULT');
        }
        
        // Filter custom options
        $custom = array_intersect_key(array_change_key_case($custom, CASE_UPPER), $defaults);
        
        // Initialize option array
        $this->options = $custom + $defaults;
        
        // Use only the first character of OPTION_DBF_NULL_PADDING_CHAR if it's set and is not false or empty
        $k = Shapefile::OPTION_DBF_NULL_PADDING_CHAR;
        if (array_key_exists($k, $this->options)) {
            $this->options[$k] = ($this->options[$k] === false || $this->options[$k] === null || $this->options[$k] === '') ? null : substr($this->options[$k], 0, 1);
        }
        
        // Parse OPTION_DBF_IGNORED_FIELDS
        $k = Shapefile::OPTION_DBF_IGNORED_FIELDS;
        if (array_key_exists($k, $this->options)) {
            $this->options[$k] = is_array($this->options[$k]) ? array_map([$this, 'sanitizeDBFFieldName'], $this->options[$k]) : [];
        }
    }
    
    /**
     * Gets option value.
     *
     * @param   string  $option     Name of the option.
     *
     * @return  string
     */
    protected function getOption($option)
    {
        return $this->options[$option];
    }
    
    /**
     * Sets option value.
     *
     * @param   string  $option     Name of the option.
     * @param   mixed   $value      Value of the option.
     */
    protected function setOption($option, $value)
    {
        $this->options[$option] = $value;
    }
    
    
    /**
     * Checks if Shapefile is of type Z.
     * 
     * @return  bool
     */
    protected function isZ()
    {
        $this->checkShapeType();
        return !$this->getOption(Shapefile::OPTION_SUPPRESS_Z) && $this->shape_type > 10 && $this->shape_type < 20;
    }
    
    /**
     * Checks if Shapefile is of type M.
     * 
     * @return  bool
     */
    protected function isM()
    {
        $this->checkShapeType();
        return !$this->getOption(Shapefile::OPTION_SUPPRESS_M) && $this->shape_type > 10;
    }
    
    
    /**
     * Gets Shapefile base type, regardless of Z and M dimensions.
     * 
     * @return  integer
     */
    protected function getBasetype()
    {
        $this->checkShapeType();
        return $this->shape_type % 10;
    }
    
    
    /**
     * Returns a valid name for DBF fields.
     *
     * Only letters, numbers and underscores are allowed, everything else is converted to underscores.
     * Truncated at 10 characters.
     *
     * @param   string  $input      Raw name to be sanitized.
     *
     * @return  string
     */
    protected function sanitizeDBFFieldName($input)
    {
        if ($input === '') {
            return $input;
        }
        $ret = substr(preg_replace('/[^a-zA-Z0-9]/', '_', $input), 0, 10);
        if ($this->getOption(Shapefile::OPTION_DBF_FORCE_ALL_CAPS)) {
            $ret = strtoupper($ret);
        }
        return $ret;
    }
    
    
    
    /////////////////////////////// PRIVATE ///////////////////////////////
    /**
     * Checks if the Shapefile has been initialized with any Geometry and if YES throws and exception.
     */
    private function checkInit()
    {
        if ($this->flag_init) {
            throw new ShapefileException(Shapefile::ERR_SHP_FILE_ALREADY_INITIALIZED);
        }
    }
    
    
    /**
     * Checks if the shape type has been set and if NOT throws and exception.
     */
    private function checkShapeType()
    {
        if ($this->shape_type === null) {
            throw new ShapefileException(Shapefile::ERR_SHP_TYPE_NOT_SET);
        }
    }
    
}
