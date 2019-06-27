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
 * Main static class used to group and expose package-wide constants.
 *
 * Efforts have been made all throughout the library to keep it compatible with an audience
 * as broader as possible and some "stylistic tradeoffs" here and there were necessary to support PHP 5.4.
 */
final class Shapefile
{   
    /**
     * Invert Polygons orientation when reading/writing a Shapefile.
     *      ESRI Shapefile specifications establish clockwise order for external rings
     *      and counterclockwise order for internal ones.
     *      Simple Features standards and GeoJSON require the opposite!
     * ShapefileReader and ShapefileWriter
     * @var bool
     */
    const OPTION_INVERT_POLYGONS_ORIENTATION = 'OPTION_INVERT_POLYGONS_ORIENTATION';
    const OPTION_INVERT_POLYGONS_ORIENTATION_DEFAULT = true;
    
    /**
     * Suppress Z dimension.
     * ShapefileReader and ShapefileWriter
     * @var bool
     */
    const OPTION_SUPPRESS_Z = 'OPTION_SUPPRESS_Z';
    const OPTION_SUPPRESS_Z_DEFAULT = false;
    
    /**
     * Suppress M dimension.
     * ShapefileReader and ShapefileWriter
     * @var bool
     */
    const OPTION_SUPPRESS_M = 'OPTION_SUPPRESS_M';
    const OPTION_SUPPRESS_M_DEFAULT = false;
    
    /**
     * Force all capitals field names in DBF files.
     * ShapefileReader and ShapefileWriter
     * @var bool
     */
    const OPTION_DBF_FORCE_ALL_CAPS = 'OPTION_DBF_FORCE_ALL_CAPS';
    const OPTION_DBF_FORCE_ALL_CAPS_DEFAULT = true;
    
    /**
     * Defines a null padding character to represent null values in DBF files.
     * ShapefileReader and ShapefileWriter
     * @var string|null
     */
    const OPTION_DBF_NULL_PADDING_CHAR = 'OPTION_DBF_NULL_PADDING_CHAR';
    const OPTION_DBF_NULL_PADDING_CHAR_DEFAULT = null;
    
    /**
     * Reads all Geometries as Multi.
     * ShapefileReader
     * @var bool
     */
    const OPTION_FORCE_MULTIPART_GEOMETRIES = 'OPTION_FORCE_MULTIPART_GEOMETRIES';
    const OPTION_FORCE_MULTIPART_GEOMETRIES_DEFAULT = false;
    
    /**
     * Ignore bounding box found in Shapefile.
     * ShapefileReader
     * @var bool
     */
    const OPTION_IGNORE_SHAPEFILE_BBOX = 'OPTION_IGNORE_SHAPEFILE_BBOX';
    const OPTION_IGNORE_SHAPEFILE_BBOX_DEFAULT = false;
    
    /**
     * Ignore Geometries bounding box found in Shapefile.
     * ShapefileReader
     * @var bool
     */
    const OPTION_IGNORE_GEOMETRIES_BBOXES = 'OPTION_IGNORE_GEOMETRIES_BBOXES';
    const OPTION_IGNORE_GEOMETRIES_BBOXES_DEFAULT = false;
    
    /**
     * Ignored fields in DBF file.
     * An array of fields to ignore when reading the DBF file.
     * ShapefileReader
     * @var array|null
     */
    const OPTION_DBF_IGNORED_FIELDS = 'OPTION_DBF_IGNORED_FIELDS';
    const OPTION_DBF_IGNORED_FIELDS_DEFAULT = null;
    
    /**
     * Return a null value for invalid dates found in DBF files.
     * ShapefileReader
     * @var bool
     */
    const OPTION_DBF_NULLIFY_INVALID_DATES = 'OPTION_DBF_NULLIFY_INVALID_DATES';
    const OPTION_DBF_NULLIFY_INVALID_DATES_DEFAULT = true;
    
    
    /** File types */
    const FILE_SHP  = 'shp';
    const FILE_SHX  = 'shx';
    const FILE_DBF  = 'dbf';
    const FILE_DBT  = 'dbt';
    const FILE_PRJ  = 'prj';
    const FILE_CPG  = 'cpg';
    const FILE_CST  = 'cst';
    
    
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
    
    
    /** DBF types */
    const DBF_TYPE_CHAR     = 'C';
    const DBF_TYPE_DATE     = 'D';
    const DBF_TYPE_LOGICAL  = 'L';
    const DBF_TYPE_MEMO     = 'M';
    const DBF_TYPE_NUMERIC  = 'N';
    
    
    /** Return format types */
    const FORMAT_INT = 0;
    const FORMAT_STR = 1;
    
    
    /** Misc */
    const DEFAULT_DBF_CHARSET   = 'ISO-8859-1';
    const EOF                   = 0;
    const DBF_MAX_FIELD_COUNT   = 255;
    const DBF_FIELD_TERMINATOR  = 0x0d;
    const DBF_EOF_MARKER        = 0x1a;
    const DBF_BLANK             = 0x20;
    const DBF_NULL              = 0x00;
    const DBT_BLOCK_SIZE        = 512;
    const DBT_FIELD_TERMINATOR  = 0x1a;
    
    
    /** Errors */
    const ERR_UNDEFINED = 'ERR_UNDEFINED';
    const ERR_UNDEFINED_MESSAGE = "Undefined error.";
    
    const ERR_FILE_MISSING = 'ERR_FILE_MISSING';
    const ERR_FILE_MISSING_MESSAGE = "A required file is missing";
    
    const ERR_FILE_EXISTS = 'ERR_FILE_EXISTS';
    const ERR_FILE_EXISTS_MESSAGE = "File not found. Check if the file exists and is readable";
    
    const ERR_FILE_OPEN = 'ERR_FILE_OPEN';
    const ERR_FILE_OPEN_MESSAGE = "Unable to read file";
    
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
    
    const ERR_DBF_FIELD_NAME_NOT_UNIQUE = 'ERR_DBF_FIELD_NAME_NOT_UNIQUE';
    const ERR_DBF_FIELD_NAME_NOT_UNIQUE_MESSAGE = "Field name must be unique in DBF file";
    
    const ERR_DBF_FIELD_NAME_NOT_VALID = 'ERR_DBF_FIELD_NAME_NOT_VALID';
    const ERR_DBF_FIELD_NAME_NOT_VALID_MESSAGE = "Field name can be maximum 10 characters and contain only numbers, digits and underscores";
    
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
    
    const ERR_GEOM_SHAPEFILE_NOT_SET = 'ERR_GEOM_SHAPEFILE_NOT_SET';
    const ERR_GEOM_SHAPEFILE_NOT_SET_MESSAGE = "Shapefile not set. Cannot retrieve data definition";
    
    const ERR_GEOM_SHAPEFILE_ALREADY_SET = 'ERR_GEOM_SHAPEFILE_ALREADY_SET';
    const ERR_GEOM_SHAPEFILE_ALREADY_SET_MESSAGE = "Shapefile already set. Cannot change Geometry or data definition";
    
    const ERR_GEOM_POINT_NOT_VALID = 'ERR_GEOM_POINT_NOT_VALID';
    const ERR_GEOM_POINT_NOT_VALID_MESSAGE = "A Point can be either EMPTY or al least 2D";
    
    const ERR_GEOM_POLYGON_AREA_TOO_SMALL = 'ERR_GEOM_POLYGON_AREA_TOO_SMALL';
    const ERR_GEOM_POLYGON_AREA_TOO_SMALL_MESSAGE = "Polygon Area too small, cannot determine vertices orientation";
    
    const ERR_GEOM_POLYGON_NOT_VALID = 'ERR_GEOM_POLYGON_NOT_VALID';
    const ERR_GEOM_POLYGON_NOT_VALID_MESSAGE = "Polygon not valid or Polygon Area too small. Please check the geometries before reading the Shapefile";
    
    const ERR_INPUT_RECORD_NOT_FOUND = 'ERR_INPUT_RECORD_NOT_FOUND';
    const ERR_INPUT_RECORD_NOT_FOUND_MESSAGE = "Record index not found (check the total number of records in the SHP file)";
    
    const ERR_INPUT_FIELD_NOT_FOUND = 'ERR_INPUT_FIELD_NOT_FOUND';
    const ERR_INPUT_FIELD_NOT_FOUND_MESSAGE = "Field name not found";
    
    const ERR_INPUT_GEOMETRY_TYPE_NOT_VALID = 'ERR_INPUT_GEOMETRY_TYPE_NOT_VALID';
    const ERR_INPUT_GEOMETRY_TYPE_NOT_VALID_MESSAGE = "Geometry type not valid. Must be of specified type";
    
    const ERR_INPUT_GEOMETRY_INDEX_NOT_VALID = 'ERR_INPUT_GEOMETRY_INDEX_NOT_VALID';
    const ERR_INPUT_GEOMETRY_INDEX_NOT_VALID_MESSAGE = "Geometry index not valid (check the total number of geometries in the collection)";
    
    const ERR_INPUT_ARRAY_NOT_VALID = 'ERR_INPUT_ARRAY_NOT_VALID';
    const ERR_INPUT_ARRAY_NOT_VALID_MESSAGE = "Array input not valid";
    
    const ERR_INPUT_WKT_NOT_VALID = 'ERR_INPUT_WKT_NOT_VALID';
    const ERR_INPUT_WKT_NOT_VALID_MESSAGE = "WKT input not valid";
    
    const ERR_INPUT_GEOJSON_NOT_VALID = 'ERR_INPUT_GEOJSON_NOT_VALID';
    const ERR_INPUT_GEOJSON_NOT_VALID_MESSAGE = "GeoJSON input not valid";
    
    
    
    /**
     * Private constructor, no instances of this class allowed.
     */
    private function __construct()
    {}
}
