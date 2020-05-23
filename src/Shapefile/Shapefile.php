<?php

/**
 * PHP Shapefile - PHP library to read and write ESRI Shapefiles, compatible with WKT and GeoJSON
 *
 * @package Shapefile
 * @author  Gaspare Sganga
 * @version 3.3.0
 * @license MIT
 * @link    https://gasparesganga.com/labs/php-shapefile/
 */

namespace Shapefile;

/**
 * Abstract base class for ShapefileReader and ShapefileWriter.
 * It provides some common public methods to both of them and exposes package-wide constants.
 *
 * Efforts have been made all throughout the library to keep it compatible with an audience
 * as broader as possible and some "stylistic tradeoffs" here and there were necessary to support PHP 5.4.
 */
abstract class Shapefile
{
    /////////////////////////////// PUBLIC CONSTANTS ///////////////////////////////
    /** Actions */
    const ACTION_IGNORE     = 0;
    const ACTION_CHECK      = 1;
    const ACTION_FORCE      = 2;
    
    /** DBF fields types */
    const DBF_TYPE_CHAR     = 'C';
    const DBF_TYPE_DATE     = 'D';
    const DBF_TYPE_LOGICAL  = 'L';
    const DBF_TYPE_MEMO     = 'M';
    const DBF_TYPE_NUMERIC  = 'N';
    const DBF_TYPE_FLOAT    = 'F';
    
    /** File types */
    const FILE_SHP  = 'shp';
    const FILE_SHX  = 'shx';
    const FILE_DBF  = 'dbf';
    const FILE_DBT  = 'dbt';
    const FILE_PRJ  = 'prj';
    const FILE_CPG  = 'cpg';
    
    /** Return formats  */
    const FORMAT_INT = 0;
    const FORMAT_STR = 1;
    
    /** File modes */
    const MODE_PRESERVE     = 0;
    const MODE_OVERWRITE    = 1;
    const MODE_APPEND       = 2;
    
    /** Polygon orientations */
    const ORIENTATION_CLOCKWISE         = 0;
    const ORIENTATION_COUNTERCLOCKWISE  = 1;
    const ORIENTATION_UNCHANGED         = 2;
    
    /** Shape types */
    const SHAPE_TYPE_NULL           = 0;
    const SHAPE_TYPE_POINT          = 1;
    const SHAPE_TYPE_POLYLINE       = 3;
    const SHAPE_TYPE_POLYGON        = 5;
    const SHAPE_TYPE_MULTIPOINT     = 8;
    const SHAPE_TYPE_POINTZ         = 11;
    const SHAPE_TYPE_POLYLINEZ      = 13;
    const SHAPE_TYPE_POLYGONZ       = 15;
    const SHAPE_TYPE_MULTIPOINTZ    = 18;
    const SHAPE_TYPE_POINTM         = 21;
    const SHAPE_TYPE_POLYLINEM      = 23;
    const SHAPE_TYPE_POLYGONM       = 25;
    const SHAPE_TYPE_MULTIPOINTM    = 28;
    
    /** Misc */
    const EOF       = 0;
    const UNDEFINED = null;
    
    
    
    /////////////////////////////// OPTIONS ///////////////////////////////
    /**
     * Number of records to keep into buffer before writing them.
     * Use a value equal or less than 0 to keep all records into buffer and write them at once.
     * ShapefileWriter
     * @var int
     */
    const OPTION_BUFFERED_RECORDS = 'OPTION_BUFFERED_RECORDS';
    const OPTION_BUFFERED_RECORDS_DEFAULT = 10;
    
    /**
     * Converts from input charset to UTF-8 all strings read from DBF files.
     * ShapefileWriter
     * @var bool
     */
    const OPTION_CPG_ENABLE_FOR_DEFAULT_CHARSET = 'OPTION_CPG_ENABLE_FOR_DEFAULT_CHARSET';
    const OPTION_CPG_ENABLE_FOR_DEFAULT_CHARSET_DEFAULT = false;
    
    /**
     * Allows a maximum field size of 255 bytes instead of 254 bytes in DBF files.
     * ShapefileReader and ShapefileWriter
     * @var bool
     */
    const OPTION_DBF_ALLOW_FIELD_SIZE_255 = 'OPTION_DBF_ALLOW_FIELD_SIZE_255';
    const OPTION_DBF_ALLOW_FIELD_SIZE_255_DEFAULT = false;
    
    /**
     * Converts from input charset to UTF-8 all strings read from DBF files.
     * ShapefileReader
     * @var bool
     */
    const OPTION_DBF_CONVERT_TO_UTF8 = 'OPTION_DBF_CONVERT_TO_UTF8';
    const OPTION_DBF_CONVERT_TO_UTF8_DEFAULT = true;
    
    /**
     * Forces all capitals field names in DBF files.
     * ShapefileReader and ShapefileWriter
     * @var bool
     */
    const OPTION_DBF_FORCE_ALL_CAPS = 'OPTION_DBF_FORCE_ALL_CAPS';
    const OPTION_DBF_FORCE_ALL_CAPS_DEFAULT = true;
    
    /**
     * Ignored fields in DBF file.
     * An array of fields to ignore when reading the DBF file.
     * ShapefileReader
     * @var array|null
     */
    const OPTION_DBF_IGNORED_FIELDS = 'OPTION_DBF_IGNORED_FIELDS';
    const OPTION_DBF_IGNORED_FIELDS_DEFAULT = null;
    
    /**
     * Defines a null padding character to represent null values in DBF files.
     * ShapefileReader and ShapefileWriter
     * @var string|null
     */
    const OPTION_DBF_NULL_PADDING_CHAR = 'OPTION_DBF_NULL_PADDING_CHAR';
    const OPTION_DBF_NULL_PADDING_CHAR_DEFAULT = null;
    
    /**
     * Returns a null value for invalid dates when reading DBF files and nullify invalid dates when writing them.
     * ShapefileReader and ShapefileWriter
     * @var bool
     */
    const OPTION_DBF_NULLIFY_INVALID_DATES = 'OPTION_DBF_NULLIFY_INVALID_DATES';
    const OPTION_DBF_NULLIFY_INVALID_DATES_DEFAULT = true;
    
    /**
     * Returns dates as DateTime objects instead of ISO strings (YYYY-MM-DD).
     * ShapefileReader
     * @var bool
     */
    const OPTION_DBF_RETURN_DATES_AS_OBJECTS = 'OPTION_DBF_RETURN_DATES_AS_OBJECTS';
    const OPTION_DBF_RETURN_DATES_AS_OBJECTS_DEFAULT = false;
    
    /**
     * Deletes empty files after closing them (only if they were passed as resource handles).
     * ShapefileWriter
     * @var bool
     */
    const OPTION_DELETE_EMPTY_FILES = 'OPTION_DELETE_EMPTY_FILES';
    const OPTION_DELETE_EMPTY_FILES_DEFAULT = true;
    
    /**
     * Enforces Geometries to have all data fields defined in Shapefile.
     * ShapefileWriter
     * @var bool
     */
    const OPTION_ENFORCE_GEOMETRY_DATA_STRUCTURE = 'OPTION_ENFORCE_GEOMETRY_DATA_STRUCTURE';
    const OPTION_ENFORCE_GEOMETRY_DATA_STRUCTURE_DEFAULT = true;
    
    /**
     * Defines behaviour with existing files with the same name.
     * Possible values:
     *    MODE_PRESERVE  : Throws Shapefile::ERR_FILE_EXISTS
     *    MODE_OVERWRITE : Overwrites existing files
     *    MODE_APPEND    : Appends new records to existing files
     * ShapefileWriter
     * @var int
     */
    const OPTION_EXISTING_FILES_MODE = 'OPTION_EXISTING_FILES_MODE';
    const OPTION_EXISTING_FILES_MODE_DEFAULT = self::MODE_PRESERVE;
    
    /**
     * Reads all Polyline and Polygon Geometries as Multi.
     * ShapefileReader
     * @var bool
     */
    const OPTION_FORCE_MULTIPART_GEOMETRIES = 'OPTION_FORCE_MULTIPART_GEOMETRIES';
    const OPTION_FORCE_MULTIPART_GEOMETRIES_DEFAULT = false;
    
    /**
     * Ignores Geometries bounding box found in Shapefile.
     * ShapefileReader
     * @var bool
     */
    const OPTION_IGNORE_GEOMETRIES_BBOXES = 'OPTION_IGNORE_GEOMETRIES_BBOXES';
    const OPTION_IGNORE_GEOMETRIES_BBOXES_DEFAULT = false;
    
    /**
     * Ignores bounding box found in Shapefile.
     * ShapefileReader
     * @var bool
     */
    const OPTION_IGNORE_SHAPEFILE_BBOX = 'OPTION_IGNORE_SHAPEFILE_BBOX';
    const OPTION_IGNORE_SHAPEFILE_BBOX_DEFAULT = false;
    
    /**
     * Defines action to perform on Polygons rings.
     * They should be closed but some software don't enforce that, creating uncompliant Shapefiles.
     * Possible values:
     *    Shapefile::ACTION_IGNORE : No action taken
     *    Shapefile::ACTION_CHECK  : Checks for open rings and eventually throws Shapefile::ERR_GEOM_POLYGON_OPEN_RING
     *    Shapefile::ACTION_FORCE  : Forces all rings to be closed in Polygons
     * ShapefileReader
     * @var int
     */
    const OPTION_POLYGON_CLOSED_RINGS_ACTION = 'OPTION_POLYGON_CLOSED_RINGS_ACTION';
    const OPTION_POLYGON_CLOSED_RINGS_ACTION_DEFAULT = self::ACTION_CHECK;
    
    /**
     * Allows Polygons orientation to be either clockwise or counterclockwise when reading Shapefiles.
     * Set to false to enforce strict ESRI Shapefile specs (clockwise outer rings and counterclockwise inner ones)
     * and raise a Shapefile::ERR_GEOM_POLYGON_WRONG_ORIENTATION error for uncompliant Shapefiles.
     * ShapefileReader
     * @var bool
     */
    const OPTION_POLYGON_ORIENTATION_READING_AUTOSENSE = 'OPTION_POLYGON_ORIENTATION_READING_AUTOSENSE';
    const OPTION_POLYGON_ORIENTATION_READING_AUTOSENSE_DEFAULT = true;
    
    /**
     * Forces a specific orientation for Polygons after reading them.
     * ESRI Shapefile specs establish clockwise orientation for outer rings and counterclockwise for inner ones,
     * GeoJSON require the opposite (counterclockwise outer rings and clockwise inner ones)
     * and Simple Features used to be the same as GeoJSON but is currently allowing both.
     * Possible values:
     *    Shapefile::ORIENTATION_CLOCKWISE        : Forces clockwise outer ring and counterclockwise inner rings
     *    Shapefile::ORIENTATION_COUNTERCLOCKWISE : Forces counterclockwise outer ring and clockwise inner rings
     *    Shapefile::ORIENTATION_UNCHANGED        : Preserves original Shapefile orientation depending on file
     * ShapefileReader
     * @var int
     */
    const OPTION_POLYGON_OUTPUT_ORIENTATION = 'OPTION_POLYGON_OUTPUT_ORIENTATION';
    const OPTION_POLYGON_OUTPUT_ORIENTATION_DEFAULT = self::ORIENTATION_COUNTERCLOCKWISE;
    
    /**
     * Suppresses M dimension.
     * ShapefileReader and ShapefileWriter
     * @var bool
     */
    const OPTION_SUPPRESS_M = 'OPTION_SUPPRESS_M';
    const OPTION_SUPPRESS_M_DEFAULT = false;
    
    /**
     * Suppresses Z dimension.
     * ShapefileReader and ShapefileWriter
     * @var bool
     */
    const OPTION_SUPPRESS_Z = 'OPTION_SUPPRESS_Z';
    const OPTION_SUPPRESS_Z_DEFAULT = false;
    
    
    
    /////////////////////////////// ERRORS ///////////////////////////////
    const ERR_UNDEFINED = 'ERR_UNDEFINED';
    const ERR_UNDEFINED_MESSAGE = "Undefined error.";
    
    const ERR_FILE_MISSING = 'ERR_FILE_MISSING';
    const ERR_FILE_MISSING_MESSAGE = "A required file is missing";
    
    const ERR_FILE_EXISTS = 'ERR_FILE_EXISTS';
    const ERR_FILE_EXISTS_MESSAGE = "Check if the file exists and is readable and/or writable";
    
    const ERR_FILE_INVALID_RESOURCE = 'ERR_FILE_INVALID_RESOURCE';
    const ERR_FILE_INVALID_RESOURCE_MESSAGE = "File pointer resource not valid";
    
    const ERR_FILE_OPEN = 'ERR_FILE_OPEN';
    const ERR_FILE_OPEN_MESSAGE = "Unable to open file";
    
    const ERR_FILE_READING = 'ERR_FILE_READING';
    const ERR_FILE_READING_MESSAGE = "Error during binary file reading";
    
    const ERR_FILE_WRITING = 'ERR_FILE_WRITING';
    const ERR_FILE_WRITING_MESSAGE = "Error during binary file writing";
    
    const ERR_SHP_TYPE_NOT_SUPPORTED = 'ERR_SHP_TYPE_NOT_SUPPORTED';
    const ERR_SHP_TYPE_NOT_SUPPORTED_MESSAGE = "Shape type not supported";
    
    const ERR_SHP_TYPE_NOT_SET = 'ERR_SHP_TYPE_NOT_SET';
    const ERR_SHP_TYPE_NOT_SET_MESSAGE = "Shape type not set";
    
    const ERR_SHP_TYPE_ALREADY_SET = 'ERR_SHP_TYPE_ALREADY_SET';
    const ERR_SHP_TYPE_ALREADY_SET_MESSAGE = "Shape type has already been set";
    
    const ERR_SHP_GEOMETRY_TYPE_NOT_COMPATIBLE = 'ERR_SHP_GEOMETRY_TYPE_NOT_COMPATIBLE';
    const ERR_SHP_GEOMETRY_TYPE_NOT_COMPATIBLE_MESSAGE = "Geometry type must be compatible with Shapefile shape type";
    
    const ERR_SHP_MISMATCHED_BBOX = 'ERR_SHP_MISMATCHED_BBOX';
    const ERR_SHP_MISMATCHED_BBOX_MESSAGE = "Bounding box must have the same dimensions as the Shapefile (2D, 3D or 4D)";
    
    const ERR_SHP_FILE_ALREADY_INITIALIZED = 'ERR_SHP_FILE_ALREADY_INITIALIZED';
    const ERR_SHP_FILE_ALREADY_INITIALIZED_MESSAGE = "Cannot change Shapefile definition after it has been initialized with data";
    
    const ERR_SHP_WRONG_RECORD_TYPE = 'ERR_SHP_WRONG_RECORD_TYPE';
    const ERR_SHP_WRONG_RECORD_TYPE_MESSAGE = "Wrong record shape type";
    
    const ERR_DBF_FILE_NOT_VALID = 'ERR_DBF_FILE_NOT_VALID';
    const ERR_DBF_FILE_NOT_VALID_MESSAGE = "DBF file doesn't seem to be a valid dBase III or dBase IV format";
    
    const ERR_DBF_MISMATCHED_FILE = 'ERR_DBF_MISMATCHED_FILE';
    const ERR_DBF_MISMATCHED_FILE_MESSAGE = "Mismatched DBF file. Number of records not corresponding to the SHP file";
    
    const ERR_DBF_EOF_REACHED = 'ERR_DBF_EOF_REACHED';
    const ERR_DBF_EOF_REACHED_MESSAGE = "End of DBF file reached. Number of records not corresponding to the SHP file";
    
    const ERR_DBF_MAX_FIELD_COUNT_REACHED = 'ERR_DBF_MAX_FIELD_COUNT_REACHED';
    const ERR_DBF_MAX_FIELD_COUNT_REACHED_MESSAGE = "Cannot add other fields, maximum number of fields in a DBF file reached";
    
    const ERR_DBF_FIELD_NAME_NOT_VALID = 'ERR_DBF_FIELD_NAME_NOT_VALID';
    const ERR_DBF_FIELD_NAME_NOT_VALID_MESSAGE = "Too many field names conflicting";
    
    const ERR_DBF_FIELD_TYPE_NOT_VALID = 'ERR_DBF_FIELD_TYPE_NOT_VALID';
    const ERR_DBF_FIELD_TYPE_NOT_VALID_MESSAGE = "Field type must be CHAR, DATE, LOGICAL, MEMO or NUMERIC";
    
    const ERR_DBF_FIELD_SIZE_NOT_VALID = 'ERR_DBF_FIELD_SIZE_NOT_VALID';
    const ERR_DBF_FIELD_SIZE_NOT_VALID_MESSAGE = "Field size incorrect according to its type";
    
    const ERR_DBF_FIELD_DECIMALS_NOT_VALID = 'ERR_DBF_FIELD_DECIMALS_NOT_VALID';
    const ERR_DBF_FIELD_DECIMALS_NOT_VALID_MESSAGE = "Field decimals incorrect according to its type";
    
    const ERR_DBF_CHARSET_CONVERSION = 'ERR_DBF_CHARSET_CONVERSION';
    const ERR_DBF_CHARSET_CONVERSION_MESSAGE = "Error during conversion from provided DBF input charset to UTF-8";
    
    const ERR_DBT_EOF_REACHED = 'ERR_DBT_EOF_REACHED';
    const ERR_DBT_EOF_REACHED_MESSAGE = "End of DBT file reached. File might be corrupted";
    
    const ERR_GEOM_NOT_EMPTY = 'ERR_GEOM_NOT_EMPTY';
    const ERR_GEOM_NOT_EMPTY_MESSAGE = "Cannot reinitialize non-empty Geometry";
    
    const ERR_GEOM_COORD_VALUE_NOT_VALID = 'ERR_GEOM_COORD_VALUE_NOT_VALID';
    const ERR_GEOM_COORD_VALUE_NOT_VALID_MESSAGE = "Invalid coordinate value";
    
    const ERR_GEOM_MISMATCHED_DIMENSIONS = 'ERR_GEOM_MISMATCHED_DIMENSIONS';
    const ERR_GEOM_MISMATCHED_DIMENSIONS_MESSAGE = "All geometries in a collection must have the same dimensions (2D, 3D or 4D)";
    
    const ERR_GEOM_MISMATCHED_BBOX = 'ERR_GEOM_MISMATCHED_BBOX';
    const ERR_GEOM_MISMATCHED_BBOX_MESSAGE = "Bounding box must have the same dimensions as the Geometry (2D, 3D or 4D)";
    
    const ERR_GEOM_MISSING_FIELD = 'ERR_GEOM_MISSING_FIELD';
    const ERR_GEOM_MISSING_FIELD_MESSAGE = "Geometry is missing a field defined in the Shapefile";
    
    const ERR_GEOM_POINT_NOT_VALID = 'ERR_GEOM_POINT_NOT_VALID';
    const ERR_GEOM_POINT_NOT_VALID_MESSAGE = "A Point can be either EMPTY or al least 2D";
    
    const ERR_GEOM_POLYGON_OPEN_RING = 'ERR_GEOM_POLYGON_OPEN_RING';
    const ERR_GEOM_POLYGON_OPEN_RING_MESSAGE = "Polygons cannot contain open rings";
    
    const ERR_GEOM_POLYGON_WRONG_ORIENTATION = 'ERR_GEOM_POLYGON_WRONG_ORIENTATION';
    const ERR_GEOM_POLYGON_WRONG_ORIENTATION_MESSAGE = "Polygon orientation not compliant with Shapefile specifications";
    
    const ERR_GEOM_RING_AREA_TOO_SMALL = 'ERR_GEOM_RING_AREA_TOO_SMALL';
    const ERR_GEOM_RING_AREA_TOO_SMALL_MESSAGE = "Ring area too small. Cannot determine ring orientation";
    
    const ERR_GEOM_RING_NOT_ENOUGH_VERTICES = 'ERR_GEOM_RING_NOT_ENOUGH_VERTICES';
    const ERR_GEOM_RING_NOT_ENOUGH_VERTICES_MESSAGE = "Not enough vertices. Cannot determine ring orientation";
    
    const ERR_INPUT_RECORD_NOT_FOUND = 'ERR_INPUT_RECORD_NOT_FOUND';
    const ERR_INPUT_RECORD_NOT_FOUND_MESSAGE = "Record index not found (check the total number of records in the SHP file)";
    
    const ERR_INPUT_FIELD_NOT_FOUND = 'ERR_INPUT_FIELD_NOT_FOUND';
    const ERR_INPUT_FIELD_NOT_FOUND_MESSAGE = "Field not found";
    
    const ERR_INPUT_GEOMETRY_TYPE_NOT_VALID = 'ERR_INPUT_GEOMETRY_TYPE_NOT_VALID';
    const ERR_INPUT_GEOMETRY_TYPE_NOT_VALID_MESSAGE = "Geometry type not valid. Must be of specified type";
    
    const ERR_INPUT_GEOMETRY_INDEX_NOT_VALID = 'ERR_INPUT_GEOMETRY_INDEX_NOT_VALID';
    const ERR_INPUT_GEOMETRY_INDEX_NOT_VALID_MESSAGE = "Geometry index not valid (check the total number of geometries in the collection)";
    
    const ERR_INPUT_ARRAY_NOT_VALID = 'ERR_INPUT_ARRAY_NOT_VALID';
    const ERR_INPUT_ARRAY_NOT_VALID_MESSAGE = "Array not valid";
    
    const ERR_INPUT_WKT_NOT_VALID = 'ERR_INPUT_WKT_NOT_VALID';
    const ERR_INPUT_WKT_NOT_VALID_MESSAGE = "WKT not valid";
    
    const ERR_INPUT_GEOJSON_NOT_VALID = 'ERR_INPUT_GEOJSON_NOT_VALID';
    const ERR_INPUT_GEOJSON_NOT_VALID_MESSAGE = "GeoJSON not valid";
    
    const ERR_INPUT_NUMERIC_VALUE_OVERFLOW = 'ERR_INPUT_NUMERIC_VALUE_OVERFLOW';
    const ERR_INPUT_NUMERIC_VALUE_OVERFLOW_MESSAGE = "Integer value overflows field size definition";
    
    
        
    /////////////////////////////// DEPRECATED CONSTANTS ///////////////////////////////
    /**
     * @deprecated  This option was deprecated with v3.3.0 and will disappear in the next releases.
     *              Use OPTION_POLYGON_CLOSED_RINGS_ACTION instead.
     */
    const OPTION_ENFORCE_POLYGON_CLOSED_RINGS = 'OPTION_ENFORCE_POLYGON_CLOSED_RINGS';
    
    /**
     * @deprecated  This option was deprecated with v3.3.0 and will disappear in the next releases.
     *              Use OPTION_POLYGON_OUTPUT_ORIENTATION instead.
     */
    const OPTION_INVERT_POLYGONS_ORIENTATION = 'OPTION_INVERT_POLYGONS_ORIENTATION';
    
    /**
     * @deprecated  This constant was deprecated with v3.3.0 and will disappear in the next releases.
     *              Use ERR_GEOM_RING_AREA_TOO_SMALL instead.
     */
    const ERR_GEOM_POLYGON_AREA_TOO_SMALL = 'ERR_GEOM_RING_AREA_TOO_SMALL';
    
    /**
     * @deprecated  This constant was deprecated with v3.3.0 and will disappear in the next releases.
     *              Use ERR_GEOM_POLYGON_WRONG_ORIENTATION instead.
     */
    const ERR_GEOM_POLYGON_NOT_VALID = 'ERR_GEOM_POLYGON_WRONG_ORIENTATION';
    
    
    
    /////////////////////////////// INTERNAL CONSTANTS ///////////////////////////////
    /** SHP files constants */
    const SHP_FILE_CODE         = 9994;
    const SHP_HEADER_SIZE       = 100;
    const SHP_NO_DATA_THRESHOLD = -1e38;
    const SHP_NO_DATA_VALUE     = -1e40;
    const SHP_VERSION           = 1000;
    /** SHX files constants */
    const SHX_HEADER_SIZE       = 100;
    const SHX_RECORD_SIZE       = 8;
    /** DBF files constants */
    const DBF_BLANK             = 0x20;
    const DBF_DEFAULT_CHARSET   = 'ISO-8859-1';
    const DBF_DELETED_MARKER    = 0x2a;
    const DBF_EOF_MARKER        = 0x1a;
    const DBF_FIELD_TERMINATOR  = 0x0d;
    const DBF_MAX_FIELD_COUNT   = 255;
    const DBF_VALUE_MASK_TRUE   = 'TtYy';
    const DBF_VALUE_FALSE       = 'F';
    const DBF_VALUE_NULL        = '?';
    const DBF_VALUE_TRUE        = 'T';
    const DBF_VERSION           = 0x03;
    const DBF_VERSION_WITH_DBT  = 0x83;
    /** DBT files constants */
    const DBT_BLOCK_SIZE        = 512;
    const DBT_FIELD_TERMINATOR  = 0x1a;
    
    /** Shape types text description */
    public static $shape_types = [
        self::SHAPE_TYPE_NULL           => 'Null Shape',
        self::SHAPE_TYPE_POINT          => 'Point',
        self::SHAPE_TYPE_POLYLINE       => 'PolyLine',
        self::SHAPE_TYPE_POLYGON        => 'Polygon',
        self::SHAPE_TYPE_MULTIPOINT     => 'MultiPoint',
        self::SHAPE_TYPE_POINTZ         => 'PointZ',
        self::SHAPE_TYPE_POLYLINEZ      => 'PolyLineZ',
        self::SHAPE_TYPE_POLYGONZ       => 'PolygonZ',
        self::SHAPE_TYPE_MULTIPOINTZ    => 'MultiPointZ',
        self::SHAPE_TYPE_POINTM         => 'PointM',
        self::SHAPE_TYPE_POLYLINEM      => 'PolyLineM',
        self::SHAPE_TYPE_POLYGONM       => 'PolygonM',
        self::SHAPE_TYPE_MULTIPOINTM    => 'MultiPointM',
    ];
    
    
    
    /////////////////////////////// PRIVATE VARIABLES ///////////////////////////////
    /**
     * @var int|null    Shapefile type.
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
    private $charset = null;
    
    /**
     * @var array   Fields definition.
     *              Every field is represented by an array with the following structure:
     *              [
     *                  "type"      => string
     *                  "size"      => int
     *                  "decimals"  => int
     *              ]
     */
    private $fields = [];
    
    /**
     * @var array   Array of file pointer resource handles.
     */
    private $files = [];
    
    /**
     * @var array   Array of canonicalized absolute pathnames of open files.
     *              It will be populated only if files are NOT passed as stream resources.
     */
    private $filenames = [];
    
    /**
     * @var array   Options.
     */
    private $options = [];
    
    /**
     * @var int     Total number of records.
     */
    private $tot_records;
    
    
    /**
     * @var bool|null   Flag to store whether the machine is big endian or not.
     */
    private $flag_big_endian_machine = null;
    
    /**
     * @var bool    Flag representing whether the Shapefile has been initialized with any Geometry or not.
     */
    private $flag_initialized = false;
    
    
    
    /////////////////////////////// PUBLIC ///////////////////////////////
    /**
     * Checks if Shapefile is of type Z.
     *
     * @return  bool
     */
    public function isZ()
    {
        $shape_type = $this->getShapeType(Shapefile::FORMAT_INT);
        return $shape_type > 10 && $shape_type < 20;
    }
    
    /**
     * Checks if Shapefile is of type M.
     *
     * @return  bool
     */
    public function isM()
    {
        return $this->getShapeType(Shapefile::FORMAT_INT) > 10;
    }
    
    
    /**
     * Gets shape type either as integer or string.
     *
     * @param   int     $format     Optional desired output format.
     *                              It can be on of the following:
     *                              - Shapefile::FORMAT_INT [default]
     *                              - Shapefile::FORMAT_STR
     *
     * @return  int|string
     */
    public function getShapeType($format = Shapefile::FORMAT_INT)
    {
        if ($this->shape_type === null) {
            throw new ShapefileException(Shapefile::ERR_SHP_TYPE_NOT_SET);
        }
        if ($format == Shapefile::FORMAT_STR) {
            return Shapefile::$shape_types[$this->shape_type];
        } else {
            return $this->shape_type;
        }
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
     * Gets PRJ well-known-text.
     *
     * @return  string
     */
    public function getPRJ()
    {
        return $this->prj;
    }
    
    
    /**
     * Gets DBF charset.
     *
     * @return  string
     */
    public function getCharset()
    {
        return $this->charset ?: Shapefile::DBF_DEFAULT_CHARSET;
    }
    
    /**
     * Sets or resets DBF charset.
     *
     * @param   mixed   $charset    Name of the charset.
     *                              Pass a falsy value (eg. false or "") to reset it to default.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    public function setCharset($charset)
    {
        $this->charset = $charset ?: Shapefile::DBF_DEFAULT_CHARSET;
        return $this;
    }
    
    
    /**
     * Gets all fields names.
     *
     * @return  array
     */
    public function getFieldsNames()
    {
        return array_keys($this->fields);
    }
    
    /**
     * Gets all fields definitions.
     *
     * @return  array
     */
    public function getFields()
    {
        return $this->fields;
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
     * @return  int
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
     * @return  int
     */
    public function getFieldDecimals($name)
    {
        return $this->getField($name)['decimals'];
    }
    
    /**
     * Gets a complete field definition.
     *
     * The returned array contains the following elements:
     *  [
     *      "type"      => string
     *      "size"      => int
     *      "decimals"  => int
     *  ]
     *
     * @param   string  $name   Name of the field.
     *
     * @return  array
     */
    public function getField($name)
    {
        $name = $this->normalizeDBFFieldNameCase($name);
        if (!isset($this->fields[$name])) {
            throw new ShapefileException(Shapefile::ERR_INPUT_FIELD_NOT_FOUND, $name);
        }
        return $this->fields[$name];
    }
    
    
    /**
     * Gets total number of records in SHP and DBF files.
     *
     * @return  int
     */
    public function getTotRecords()
    {
        return $this->tot_records;
    }
    
    
    
    /////////////////////////////// PROTECTED ///////////////////////////////
    /**
     * Opens file pointer resource handles to specified files with binary read or write access.
     *
     * (Filenames are mapped here because files are closed in destructors and working directory may be different!)
     *
     * @param   string|array    $files          Path to SHP file / Array of paths / Array of resource handles of individual files.
     * @param   bool            $write_access   Access type: false = read; true = write.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    protected function openFiles($files, $write_access)
    {
        // Create $files array from single string (SHP filename)
        if (is_string($files)) {
            $basename = (substr($files, -4) == '.' . Shapefile::FILE_SHP) ? substr($files, 0, -4) : $files;
            $files = [
                Shapefile::FILE_SHP => $basename . '.' . Shapefile::FILE_SHP,
                Shapefile::FILE_SHX => $basename . '.' . Shapefile::FILE_SHX,
                Shapefile::FILE_DBF => $basename . '.' . Shapefile::FILE_DBF,
                Shapefile::FILE_DBT => $basename . '.' . Shapefile::FILE_DBT,
                Shapefile::FILE_PRJ => $basename . '.' . Shapefile::FILE_PRJ,
                Shapefile::FILE_CPG => $basename . '.' . Shapefile::FILE_CPG,
            ];
        }
        
        // Make sure required files are specified
        if (!is_array($files) || !isset($files[Shapefile::FILE_SHP])) {
            throw new ShapefileException(Shapefile::ERR_FILE_MISSING, strtoupper(Shapefile::FILE_SHP));
        }
        if (!is_array($files) || !isset($files[Shapefile::FILE_SHX])) {
            throw new ShapefileException(Shapefile::ERR_FILE_MISSING, strtoupper(Shapefile::FILE_SHX));
        }
        if (!is_array($files) || !isset($files[Shapefile::FILE_DBF])) {
            throw new ShapefileException(Shapefile::ERR_FILE_MISSING, strtoupper(Shapefile::FILE_DBF));
        }
        
        
        $mode = $write_access ? 'c+b' : 'rb';
        if ($files === array_filter($files, 'is_resource')) {
            // Resource handles
            foreach ($files as $type => $file) {
                $file_mode = stream_get_meta_data($file)['mode'];
                if (
                        get_resource_type($file) != 'stream'
                    ||  (!$write_access && !in_array($file_mode, array('rb', 'r+b', 'w+b', 'x+b', 'c+b')))
                    ||  ($write_access && !in_array($file_mode, array('r+b', 'wb', 'w+b', 'xb', 'x+b', 'cb', 'c+b')))
                ) {
                    throw new ShapefileException(Shapefile::ERR_FILE_INVALID_RESOURCE, strtoupper($type));
                }
                $this->files[$type] = $file;
            }
            $this->filenames = [];
        } else {
            // Filenames
            foreach (
                [
                    Shapefile::FILE_SHP => true,
                    Shapefile::FILE_SHX => true,
                    Shapefile::FILE_DBF => true,
                    Shapefile::FILE_DBT => false,
                    Shapefile::FILE_PRJ => false,
                    Shapefile::FILE_CPG => false,
                ] as $type => $required
            ) {
                if (isset($files[$type])) {
                    if (
                            (!$write_access && is_string($files[$type]) && is_readable($files[$type]) && is_file($files[$type]))
                        ||  ($write_access && is_string($files[$type]) && is_writable(dirname($files[$type])) && (!file_exists($files[$type]) || ($this->getOption(Shapefile::OPTION_EXISTING_FILES_MODE) != Shapefile::MODE_PRESERVE && is_readable($files[$type]) && is_file($files[$type]))))
                    ) {
                        $handle = fopen($files[$type], $mode);
                        if ($handle === false) {
                            throw new ShapefileException(Shapefile::ERR_FILE_OPEN, $files[$type]);
                        }
                        $this->files[$type]     = $handle;
                        $this->filenames[$type] = realpath(stream_get_meta_data($handle)['uri']);
                    } elseif ($required) {
                        throw new ShapefileException(Shapefile::ERR_FILE_EXISTS, $files[$type]);
                    }
                }
            }
        }
        foreach (array_keys($this->files) as $file_type) {
            $this->setFilePointer($file_type, 0);
        }
        
        return $this;
    }
    
    /**
     * Closes all open resource handles.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    protected function closeFiles()
    {
        if (count($this->filenames) > 0) {
            foreach ($this->files as $handle) {
                fclose($handle);
            }
        }
        return $this;
    }
    
    /**
     * Truncates an open resource handle to a given length.
     *
     * @param   string  $file_type  File type.
     * @param   int     $size       Optional size to truncate to.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    protected function fileTruncate($file_type, $size = 0)
    {
        ftruncate($this->files[$file_type], $size);
        return $this;
    }
    
    /**
     * Checks if file type has been opened.
     *
     * @param   string  $file_type  File type.
     *
     * @return  bool
     */
    protected function isFileOpen($file_type)
    {
        return isset($this->files[$file_type]);
    }
    
    /**
     * Gets an array of the open resource handles.
     *
     * @return  array
     */
    protected function getFiles()
    {
        return $this->files;
    }
    
    /**
     * Gets an array of canonicalized absolute pathnames if files were NOT passed as stream resources, or an empty array if they were.
     *
     * @return  array
     */
    protected function getFilenames()
    {
        return $this->filenames;
    }
    
    /**
     * Gets size of an open a resource handle.
     *
     * @param   string  $file_type  File type (member of $this->files array).
     *
     * @return  int
     */
    protected function getFileSize($file_type)
    {
        return fstat($this->files[$file_type])['size'];
    }
    
    /**
     * Gets current pointer position of a resource handle.
     *
     * @param   string  $file_type  File type (member of $this->files array).
     *
     * @return  int
     */
    protected function getFilePointer($file_type)
    {
        return ftell($this->files[$file_type]);
    }
    
    /**
     * Sets the pointer position of a resource handle to specified value.
     *
     * @param   string  $file_type  File type (member of $this->files array).
     * @param   int     $position   The position to set the pointer to.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    protected function setFilePointer($file_type, $position)
    {
        fseek($this->files[$file_type], $position, SEEK_SET);
        return $this;
    }
    
    /**
     * Resets the pointer position of a resource handle to its end.
     *
     * @param   string  $file_type  File type (member of $this->files array).
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    protected function resetFilePointer($file_type)
    {
        fseek($this->files[$file_type], 0, SEEK_END);
        return $this;
    }
    
    /**
     * Increase the pointer position of a resource handle of specified value.
     *
     * @param   string  $file_type  File type (member of $this->files array).
     * @param   int     $offset     The offset to move the pointer for.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    protected function setFileOffset($file_type, $offset)
    {
        fseek($this->files[$file_type], $offset, SEEK_CUR);
        return $this;
    }
    
    /**
     * Reads data from an open resource handle.
     *
     * @param   string  $file_type      File type.
     * @param   int     $length         Number of bytes to read.
     *
     * @return  string
     */
    protected function readData($file_type, $length)
    {
        $ret = @fread($this->files[$file_type], $length);
        if ($ret === false) {
            throw new ShapefileException(Shapefile::ERR_FILE_READING);
        }
        return $ret;
    }
    
    /**
     * Writes binary string packed data to an open resource handle.
     *
     * @param   string  $file_type      File type.
     * @param   string  $data           Binary string packed data to write.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    protected function writeData($file_type, $data)
    {
        if (@fwrite($this->files[$file_type], $data) === false) {
            throw new ShapefileException(Shapefile::ERR_FILE_WRITING);
        }
        return $this;
    }
    
    /**
     * Checks if machine is big endian.
     *
     * @return  bool
     */
    protected function isBigEndianMachine()
    {
        if ($this->flag_big_endian_machine === null) {
            $this->flag_big_endian_machine = current(unpack('v', pack('S', 0xff))) !== 0xff;
        }
        return $this->flag_big_endian_machine;
    }
    
    
    /**
     * Initializes options with default and user-provided values.
     *
     * @param   array   $options    Array of options to initialize.
     * @param   array   $custom     User-provided options
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    protected function initOptions($options, $custom)
    {
        // Make sure compulsory options used in this abstract class are defined
        $options = array_unique(array_merge($options, [
            Shapefile::OPTION_DBF_ALLOW_FIELD_SIZE_255,
            Shapefile::OPTION_DBF_FORCE_ALL_CAPS,
            Shapefile::OPTION_SUPPRESS_M,
            Shapefile::OPTION_SUPPRESS_Z,
        ]));
        
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
            $this->options[$k] = is_array($this->options[$k]) ? array_map([$this, 'normalizeDBFFieldNameCase'], $this->options[$k]) : [];
        }
        
        return $this;
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
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    protected function setOption($option, $value)
    {
        $this->options[$option] = $value;
        return $this;
    }
    
    /**
     * Sets shape type.
     * It can be called just once for an instance of the class.
     *
     * @param   int     $type   Shape type. It can be on of the following:
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
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    protected function setShapeType($type)
    {
        if ($this->shape_type !== null) {
            throw new ShapefileException(Shapefile::ERR_SHP_TYPE_ALREADY_SET);
        }
        if (!isset(Shapefile::$shape_types[$type])) {
            throw new ShapefileException(Shapefile::ERR_SHP_TYPE_NOT_SUPPORTED, $type);
        }
        $this->shape_type = $type;
        return $this;
    }
    
    /**
     * Gets Shapefile base type, regardless of Z and M dimensions.
     *
     * @return  int
     */
    protected function getBasetype()
    {
        return $this->getShapeType(Shapefile::FORMAT_INT) % 10;
    }
    
    
    /**
     * Overwrites computed bounding box for the Shapefile.
     * No check is carried out except a formal compliance of dimensions.
     *
     * @param   array   $bounding_box   Associative array with the xmin, xmax, ymin, ymax and optional zmin, zmax, mmin, mmax values.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    protected function overwriteComputedBoundingBox($bounding_box)
    {
        $this->computed_bounding_box = $this->sanitizeBoundingBox($bounding_box);
        return $this;
    }
    
    /**
     * Sets a custom bounding box for the Shapefile.
     * No check is carried out except a formal compliance of dimensions.
     *
     * @param   array   $bounding_box   Associative array with the xmin, xmax, ymin, ymax and optional zmin, zmax, mmin, mmax values.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    protected function setCustomBoundingBox($bounding_box)
    {
        $this->custom_bounding_box = $this->sanitizeBoundingBox($bounding_box);
        return $this;
    }
    
    /**
     * Resets custom bounding box for the Shapefile.
     * It will cause getBoundingBox() method to return a normally computed bbox instead of a custom one.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    protected function resetCustomBoundingBox()
    {
        $this->custom_bounding_box = null;
        return $this;
    }
    
    
    /**
     * Sets PRJ well-known-text.
     *
     * @param   string  $prj    PRJ well-known-text.
     *                          Pass a falsy value (eg. false or "") to delete it.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    protected function setPRJ($prj)
    {
        $this->prj = $prj ?: null;
        return $this;
    }
    
    
    /**
     * Sets current total number of records.
     *
     * @param   int     $tot_records    Total number of records currently in the files.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    protected function setTotRecords($tot_records)
    {
        $this->tot_records = $tot_records;
        return $this;
    }
    
    /**
     * Gets the state of the initialized flag.
     *
     * @return  bool
     */
    protected function isInitialized()
    {
        return $this->flag_initialized;
    }
    
    /**
     * Sets the state of the initialized flag.
     *
     * @param   bool    $value
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    protected function setFlagInitialized($value)
    {
        $this->flag_initialized = $value;
        return $this;
    }
    
    
    /**
     * Adds a field to the shapefile definition.
     * Returns the effective field name after eventual sanitization.
     *
     * @param   string  $name               Name of the field. Invalid names will be sanitized
     *                                      (maximum 10 characters, only letters, numbers and underscores are allowed).
     * @param   string  $type               Type of the field. It can be on of the following:
     *                                      - Shapefile::DBF_TYPE_CHAR
     *                                      - Shapefile::DBF_TYPE_DATE
     *                                      - Shapefile::DBF_TYPE_LOGICAL
     *                                      - Shapefile::DBF_TYPE_MEMO
     *                                      - Shapefile::DBF_TYPE_NUMERIC
     *                                      - Shapefile::DBF_TYPE_FLOAT
     * @param   int     $size               Lenght of the field, depending on the type.
     * @param   int     $decimals           Optional number of decimal digits for numeric type.
     *
     * @return  string
     */
    protected function addField($name, $type, $size, $decimals)
    {
        // Check init
        if ($this->isInitialized()) {
            throw new ShapefileException(Shapefile::ERR_SHP_FILE_ALREADY_INITIALIZED);
        }
        // Check filed count
        if (count($this->fields) >= Shapefile::DBF_MAX_FIELD_COUNT) {
            throw new ShapefileException(Shapefile::ERR_DBF_MAX_FIELD_COUNT_REACHED, Shapefile::DBF_MAX_FIELD_COUNT);
        }
        
        // Sanitize name and normalize case
        $name = $this->normalizeDBFFieldNameCase($this->sanitizeDBFFieldName($name));
        
        // Check type
        if (
                $type !== Shapefile::DBF_TYPE_CHAR
            &&  $type !== Shapefile::DBF_TYPE_DATE
            &&  $type !== Shapefile::DBF_TYPE_LOGICAL
            &&  $type !== Shapefile::DBF_TYPE_MEMO
            &&  $type !== Shapefile::DBF_TYPE_NUMERIC
            &&  $type !== Shapefile::DBF_TYPE_FLOAT
        ) {
            throw new ShapefileException(Shapefile::ERR_DBF_FIELD_TYPE_NOT_VALID, $type);
        }
        
        // Check size
        $size       = intval($size);
        $max_size   = $this->getOption(Shapefile::OPTION_DBF_ALLOW_FIELD_SIZE_255) ? 255 : 254;
        if (
                ($size < 1)
            ||  ($type == Shapefile::DBF_TYPE_CHAR && $size > $max_size)
            ||  ($type == Shapefile::DBF_TYPE_DATE && $size !== 8)
            ||  ($type == Shapefile::DBF_TYPE_LOGICAL && $size !== 1)
            ||  ($type == Shapefile::DBF_TYPE_MEMO && $size !== 10)
            ||  ($type == Shapefile::DBF_TYPE_NUMERIC && $size > $max_size)
            ||  ($type == Shapefile::DBF_TYPE_FLOAT && $size > $max_size)
        ) {
            throw new ShapefileException(Shapefile::ERR_DBF_FIELD_SIZE_NOT_VALID, $size);
        }
        
        // Minimal decimal formal check
        $decimals = intval($decimals);
        if (
                ($type != Shapefile::DBF_TYPE_NUMERIC && $type != Shapefile::DBF_TYPE_FLOAT && $decimals !== 0)
            ||  ($type == Shapefile::DBF_TYPE_FLOAT && $decimals === 0)
            ||  ($decimals < 0)
            ||  ($decimals > 0 && $size - 1 <= $decimals)
        ) {
            throw new ShapefileException(Shapefile::ERR_DBF_FIELD_DECIMALS_NOT_VALID, $type . ' - ' . $decimals);
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
     * Normalize field name case according to OPTION_DBF_FORCE_ALL_CAPS status.
     *
     * @param   string  $input      Field name to be case-normalized.
     *
     * @return  string
     */
    protected function normalizeDBFFieldNameCase($input)
    {
        return $this->getOption(Shapefile::OPTION_DBF_FORCE_ALL_CAPS) ? strtoupper($input) : $input;
    }
    
    
    /**
     * Pairs a Geometry with the Shapefile.
     * It enforces the Geometry type and computes Shapefile bounding box.
     * After that the Shapefile will be considered as "initialized" and no changes will be allowd to its structure.
     *
     * @param   \Shapefile\Geometry\Geometry    $Geometry   Geometry to pair with.
     *
     * @return  self    Returns $this to provide a fluent interface.
     */
    protected function pairGeometry(Geometry\Geometry $Geometry)
    {
        // Geometry type
        if (
                $this->getBasetype() !== $Geometry->getSHPBasetype()
            ||  (!$Geometry->isEmpty() && $Geometry->isZ() !== $this->isZ() && !$this->getOption(Shapefile::OPTION_SUPPRESS_Z))
            ||  (!$Geometry->isEmpty() && $Geometry->isM() !== $this->isM() && !$this->getOption(Shapefile::OPTION_SUPPRESS_M))
        ) {
            throw new ShapefileException(Shapefile::ERR_SHP_GEOMETRY_TYPE_NOT_COMPATIBLE, $this->getShapeType(Shapefile::FORMAT_INT) . ' - ' . $this->getShapeType(Shapefile::FORMAT_STR));
        }
        
        // Bounding box
        $bbox = $Geometry->getBoundingBox();
        if (!$this->computed_bounding_box && $bbox) {
            if ($this->getOption(Shapefile::OPTION_SUPPRESS_Z)) {
                unset($bbox['zmin'], $bbox['zmax']);
            }
            if ($this->getOption(Shapefile::OPTION_SUPPRESS_M)) {
                unset($bbox['mmin'], $bbox['mmax']);
            }
            $this->computed_bounding_box = $bbox;
        } elseif ($bbox) {
            if ($bbox['xmin'] < $this->computed_bounding_box['xmin']) {
                $this->computed_bounding_box['xmin'] = $bbox['xmin'];
            }
            if ($bbox['xmax'] > $this->computed_bounding_box['xmax']) {
                $this->computed_bounding_box['xmax'] = $bbox['xmax'];
            }
            if ($bbox['ymin'] < $this->computed_bounding_box['ymin']) {
                $this->computed_bounding_box['ymin'] = $bbox['ymin'];
            }
            if ($bbox['ymax'] > $this->computed_bounding_box['ymax']) {
                $this->computed_bounding_box['ymax'] = $bbox['ymax'];
            }
            if ($this->isZ() && !$this->getOption(Shapefile::OPTION_SUPPRESS_Z)) {
                if ($bbox['zmin'] < $this->computed_bounding_box['zmin']) {
                    $this->computed_bounding_box['zmin'] = $bbox['zmin'];
                }
                if ($bbox['zmax'] > $this->computed_bounding_box['zmax']) {
                    $this->computed_bounding_box['zmax'] = $bbox['zmax'];
                }
            }
            if ($this->isM() && !$this->getOption(Shapefile::OPTION_SUPPRESS_M)) {
                if ($this->computed_bounding_box['mmin'] === false || $bbox['mmin'] < $this->computed_bounding_box['mmin']) {
                    $this->computed_bounding_box['mmin'] = $bbox['mmin'];
                }
                if ($this->computed_bounding_box['mmax'] === false || $bbox['mmax'] > $this->computed_bounding_box['mmax']) {
                    $this->computed_bounding_box['mmax'] = $bbox['mmax'];
                }
            }
        }
        
        // Mark Shapefile as initialized
        $this->setFlagInitialized(true);
        
        return $this;
    }
    
    
    
    /////////////////////////////// PRIVATE ///////////////////////////////
    /**
     * Checks formal compliance of a bounding box dimensions.
     *
     * @param   array   $bounding_box   Associative array with the xmin, xmax, ymin, ymax and optional zmin, zmax, mmin, mmax values.
     */
    private function sanitizeBoundingBox($bounding_box)
    {
        $bounding_box = array_intersect_key($bounding_box, array_flip(['xmin', 'xmax', 'ymin', 'ymax', 'zmin', 'zmax', 'mmin', 'mmax']));
        if ($this->getOption(Shapefile::OPTION_SUPPRESS_Z)) {
            unset($bounding_box['zmin'], $bounding_box['zmax']);
        }
        if ($this->getOption(Shapefile::OPTION_SUPPRESS_M)) {
            unset($bounding_box['mmin'], $bounding_box['mmax']);
        }
        
        if (
            !isset($bounding_box['xmin'], $bounding_box['xmax'], $bounding_box['ymin'], $bounding_box['ymax'])
            || (
                ($this->isZ() && !$this->getOption(Shapefile::OPTION_SUPPRESS_Z) && !isset($bounding_box['zmin'], $bounding_box['zmax']))
                || (!$this->isZ() && (isset($bounding_box['zmin']) || isset($bounding_box['zmax'])))
            )
            || (
                ($this->isM() && !$this->getOption(Shapefile::OPTION_SUPPRESS_M) && !isset($bounding_box['mmin'], $bounding_box['mmax']))
                || (!$this->isM() && (isset($bounding_box['mmin']) || isset($bounding_box['mmax'])))
            )
        ) {
            throw new ShapefileException(Shapefile::ERR_SHP_MISMATCHED_BBOX);
        }
        
        return $bounding_box;
    }
    
    
    /**
     * Returns a valid name for a DBF field.
     *
     * Only letters, numbers and underscores are allowed, everything else is converted to underscores.
     * Field names get truncated to 10 characters and conflicting ones are truncated to 8 characters adding a number from 1 to 99.
     *
     * @param   string  $input      Raw name to be sanitized.
     *
     * @return  string
     */
    private function sanitizeDBFFieldName($input)
    {
        if ($input === '') {
            return $input;
        }
        
        $ret        = substr(preg_replace('/[^a-zA-Z0-9]/', '_', $input), 0, 10);
        $fieldnames = array_fill_keys(array_keys(array_change_key_case($this->fields, CASE_UPPER)), true);
        if (isset($fieldnames[strtoupper($ret)])) {
            $ret = substr($ret, 0, 8) . '_1';
            while (isset($fieldnames[strtoupper($ret)])) {
                $n = intval(trim(substr($ret, -2), '_')) + 1;
                if ($n > 99) {
                    throw new ShapefileException(Shapefile::ERR_DBF_FIELD_NAME_NOT_VALID, $input);
                }
                $ret = substr($ret, 0, -2) . str_pad($n, 2, '_', STR_PAD_LEFT);
            }
        }
        return $ret;
    }
}
