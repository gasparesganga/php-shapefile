# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/) 
and this project adheres to [Semantic Versioning](https://semver.org/).


## v3.4.0 - 2021-01-23
### Added
- Full GeoJSON *Feature* support with properties data
- Capability to ignore *DBF* and *SHX* files to recover corrupted Shapefiles
- ShapefileReader constructor options constants:
    - `Shapefile::OPTION_IGNORE_FILE_DBF`
    - `Shapefile::OPTION_IGNORE_FILE_SHX`
- `ShapefileReader::getTotRecords()` output constant:
    - `Shapefile::UNKNOWN`
- Error types constants:
    - `Shapefile::ERR_INPUT_RANDOM_ACCESS_UNAVAILABLE`

### Changed
- Improved handling of *Logical* fields in *DBF* files
- Increased tolerance coefficient to deal with extremely small areas when determining ring orientation

### Fixed
- Handling of unspecified bounding box in *SHP* and *SHX* file headers for empty Shapefiles
- Behaviour with *DBF* files for empty Shapefiles



## v3.3.3 - 2020-09-17
### Fixed
- Bug in `ShapefileWriter::packPoint()` method affecting *Z* and *M* geometries



## v3.3.2 - 2020-08-17
### Fixed
- Removed duplicated doc block header from `ShapefileWriter.php` file



## v3.3.1 - 2020-08-13
### Fixed
- Prevented a PHP warning when encoding a boolean `false` value for a `Shapefile::DBF_TYPE_LOGICAL` field



## v3.3.0 - 2020-05-23
### Added
- `Shapefile\Geometry\Linestring` public methods:
    - `Shapefile\Geometry\Linestring::isClockwise()`
    - `Shapefile\Geometry\Linestring::forceClockwise()`
    - `Shapefile\Geometry\Linestring::forceCounterClockwise()`
    - `Shapefile\Geometry\Linestring::forceClosedRing()`
- `Shapefile\Geometry\Polygon` public methods:
    - `Shapefile\Geometry\Polygon::isClockwise()`
    - `Shapefile\Geometry\Polygon::isCounterClockwise()`
    - `Shapefile\Geometry\Polygon::forceClockwise()`
    - `Shapefile\Geometry\Polygon::forceCounterClockwise()`
    - `Shapefile\Geometry\Polygon::forceClosedRings()`
- `Shapefile\Geometry\MultiPolygon` public methods:
    - `Shapefile\Geometry\MultiPolygon::isClockwise()`
    - `Shapefile\Geometry\MultiPolygon::isCounterClockwise()`
    - `Shapefile\Geometry\MultiPolygon::forceClockwise()`
    - `Shapefile\Geometry\MultiPolygon::forceCounterClockwise()`
    - `Shapefile\Geometry\MultiPolygon::forceClosedRings()`
- `Shapefile\Geometry\GeometryCollection::reverseGeometries()` protected method.
- `Shapefile\Geometry\Polygon` and `Shapefile\Geometry\MultiPolygon` optional constructor parameter `$force_orientation`.
- `Shapefile\ShapefileReader` constructor options:
    - `Shapefile::OPTION_POLYGON_CLOSED_RINGS_ACTION`
    - `Shapefile::OPTION_POLYGON_ORIENTATION_READING_AUTOSENSE`
    - `Shapefile::OPTION_POLYGON_OUTPUT_ORIENTATION`
- Action constants:
    - `Shapefile::ACTION_IGNORE`
    - `Shapefile::ACTION_CHECK`
    - `Shapefile::ACTION_FORCE`
 - Polygon orientation constants:
    - `Shapefile::ORIENTATION_CLOCKWISE`
    - `Shapefile::ORIENTATION_COUNTERCLOCKWISE`
    - `Shapefile::ORIENTATION_UNCHANGED`
- Error types constants:
    - `Shapefile::ERR_GEOM_RING_AREA_TOO_SMALL`
    - `Shapefile::ERR_GEOM_RING_NOT_ENOUGH_VERTICES`
- Other constants:
    - `Shapefile::UNDEFINED`
- Library implements a *fluent interface* that allows method chaining.

### Changed
- `Shapefile\Geometry\Polygon` and `Shapefile\Geometry\MultiPolygon` constructor parameter `$flag_enforce_closed_rings` is now `$closed_rings` and accepts `Shapefile::ACTION_IGNORE`, `Shapefile::ACTION_CHECK` and `Shapefile::ACTION_FORCE` values.
- Code is now PSR-12 compliant

### Deprecated
- `Shapefile\ShapefileReader` constructor options that will disappear in the next releases:
    - `Shapefile::OPTION_ENFORCE_POLYGON_CLOSED_RINGS`. Use `Shapefile::OPTION_POLYGON_CLOSED_RINGS_ACTION` instead.
    - `Shapefile::OPTION_INVERT_POLYGONS_ORIENTATION`. Use `Shapefile::OPTION_POLYGON_OUTPUT_ORIENTATION` instead.
- Constants:
    - `Shapefile::ERR_GEOM_POLYGON_AREA_TOO_SMALL`. Use `Shapefile::ERR_GEOM_RING_AREA_TOO_SMALL` instead.
    - `Shapefile::ERR_GEOM_POLYGON_NOT_VALID`. Use `Shapefile::ERR_GEOM_POLYGON_WRONG_ORIENTATION` instead.



## v3.2.0 - 2020-04-09
### Added
- `Shapefile::OPTION_DBF_ALLOW_FIELD_SIZE_255` constructor option for both `ShapefileReader` and `ShapefileWriter` classes



## v3.1.3 - 2020-02-02
### Fixed
- Changed `GEOJSON_BASETYPE` constants for `Shapefile\Geometry\Linestring` and `Shapefile\Geometry\MultiLinestring` respectively to `'LineString'` and `'MultiLineString'`



## v3.1.2 - 2020-01-15
### Fixed
- Do not apply `Shapefile::OPTION_FORCE_MULTIPART_GEOMETRIES` to Point Shapefiles



## v3.1.1 - 2019-11-10
### Fixed
- Truncate *PRJ* and *CPG* files before writing them to prevent content to be appended when `Shapefile::OPTION_EXISTING_FILES_MODE` is set to `Shapefile::MODE_APPEND`
- Increased maximum number of fields in *DBF* files to 255



## v3.1.0 - 2019-10-30
### Added
- Writing buffer in `ShapefileWriter`. It allows up to a 50% reduction in writing time
- `ShapefileWriter::flushBuffer()` method
- `Shapefile::OPTION_BUFFERED_RECORDS` constructor option for `ShapefileWriter` class
- Capability to append records to existing Shapefiles
- `Shapefile::OPTION_EXISTING_FILES_MODE` constructor option for `ShapefileWriter` class
- `Shapefile::MODE_PRESERVE`, `Shapefile::MODE_APPEND`, `Shapefile::MODE_OVERWRITE` constants
- `isZ()`, `isM()` and `getFieldsNames()` methods for both `ShapefileReader` and `ShapefileWriter` classes
- `ShapefileWriter` exposes `getShapeType()`, `getBoundingBox()`, `getPRJ()`, `getCharset()`, `setCharset()`, `getFieldsNames()`, `getField()`, `getFieldType()`, `getFieldSize()`, `getFieldDecimals()`, `getFields()` and `getTotRecords()` public methods like `ShapefileReader`
- Sanitize and accept conflicting and duplicated field names
- Other minor code and performance improvements across the library

### Changed
- Improved `GeometryCollection::getBoundingBox()` method for better performance
- Improved `ShapefileWriter::encodeFieldValue()` method for better performance, relying on PHP `number_format()` for non-textual numeric input
- Field name sanitization is always carried out in all `ShapefileWriter` field-adding methods
- `Shapefile::ERR_DBF_FIELD_NAME_NOT_VALID` error type is now defined as `"Too many field names conflicting"`
- Default `c+b` file access mode for `ShapefileWriter` class

### Fixed
- Convert field names to uppercase for `Geometry` data when `Shapefile::OPTION_DBF_FORCE_ALL_CAPS` is enabled
- Decoupling field names sanitization and `Shapefile::OPTION_DBF_FORCE_ALL_CAPS` option
- `Shapefile::ERR_GEOM_MISSING_FIELD` exception was erroneously raised when a field had an explicit `null` value and `Shapefile::OPTION_ENFORCE_GEOMETRY_DATA_STRUCTURE` was enabled
- Suppress PHP warnings in `fread()` and `fwrite()` calls: a `ShapefileException` is thrown anyways
- Bug causing a corrupted *DBF* file when a `Shapefile::ERR_GEOM_MISSING_FIELD` is raised
- Bug causing wrong record number to be written in *SHP* record headers (count starts from `1`, not from `0`)
- Corner case bug affecting `ShapefileWriter` destructor when no record has been written yet

### Removed
- `$flag_sanitize_name` parameter from all field-adding methods
- `Shapefile::OPTION_OVERWRITE_EXISTING_FILES` constructor option for `ShapefileWriter` class
- `Shapefile::ERR_DBF_FIELD_NAME_NOT_UNIQUE` error type



## v3.0.2 - 2019-09-23
### Fixed
- A *Declaration of X must be compatible with Y* PHP7 Warning thrown for `Polygon` and `MultiPolygon` `addGeometry` protected methods



## v3.0.1 - 2019-08-31
### Fixed
- A typo in a variable name introduced with a late *code clean-up before final release* that was causing a PHP Notice when reading Shapefiles with *Memo* fields
- Year in the release date of v3 in CHANGELOG file.



## v3.0.0 - 2019-08-30
### Added
- Complete OOP style refactoring
- Shapefile writing capabilities
- PHPDoc style comments
- `Shapefile\Geometry` namespace with `Point`, `MultiPoint`, `Linestring`, `MultiLinestring`, `Polygon` and `MultiPolygon` classes
- `ShapefileReader` and `ShapefileWriter` classes
- `Shapefile`, `Geometry` and `GeometryCollection` abstract classes
- Custom *DBF* charset support
- Support for emulated `null` values in *DBF* files
- Reading and writing optional *DBT* files (support for `MEMO` fields)
- Reading and writing optional *CPG* files
- `ShapefileException::getDetails()` method
- Constructor options constants:
    - `Shapefile::OPTION_CPG_ENABLE_FOR_DEFAULT_CHARSET`
    - `Shapefile::OPTION_DBF_CONVERT_TO_UTF8`
    - `Shapefile::OPTION_DBF_FORCE_ALL_CAPS`
    - `Shapefile::OPTION_DBF_IGNORED_FIELDS`
    - `Shapefile::OPTION_DBF_NULL_PADDING_CHAR`
    - `Shapefile::OPTION_DBF_NULLIFY_INVALID_DATES`
    - `Shapefile::OPTION_DBF_RETURN_DATES_AS_OBJECTS`
    - `Shapefile::OPTION_DELETE_EMPTY_FILES`
    - `Shapefile::OPTION_ENFORCE_GEOMETRY_DATA_STRUCTURE`
    - `Shapefile::OPTION_ENFORCE_POLYGON_CLOSED_RINGS`
    - `Shapefile::OPTION_FORCE_MULTIPART_GEOMETRIES`
    - `Shapefile::OPTION_IGNORE_GEOMETRIES_BBOXES`
    - `Shapefile::OPTION_IGNORE_SHAPEFILE_BBOX`
    - `Shapefile::OPTION_INVERT_POLYGONS_ORIENTATION`
    - `Shapefile::OPTION_OVERWRITE_EXISTING_FILES`
    - `Shapefile::OPTION_SUPPRESS_M`
    - `Shapefile::OPTION_SUPPRESS_Z`
- File types constants:
    - `Shapefile::FILE_SHP`
    - `Shapefile::FILE_SHX`
    - `Shapefile::FILE_DBF`
    - `Shapefile::FILE_DBT`
    - `Shapefile::FILE_PRJ`
    - `Shapefile::FILE_CPG`
- Shape types constants:
    - `Shapefile::SHAPE_TYPE_NULL`
    - `Shapefile::SHAPE_TYPE_POINT`
    - `Shapefile::SHAPE_TYPE_POLYLINE`
    - `Shapefile::SHAPE_TYPE_POLYGON`
    - `Shapefile::SHAPE_TYPE_MULTIPOINT`
    - `Shapefile::SHAPE_TYPE_POINTZ`
    - `Shapefile::SHAPE_TYPE_POLYLINEZ`
    - `Shapefile::SHAPE_TYPE_POLYGONZ`
    - `Shapefile::SHAPE_TYPE_MULTIPOINTZ`
    - `Shapefile::SHAPE_TYPE_POINTM`
    - `Shapefile::SHAPE_TYPE_POLYLINEM`
    - `Shapefile::SHAPE_TYPE_POLYGONM`
    - `Shapefile::SHAPE_TYPE_MULTIPOINTM`
- *DBF* fields types constants:
    - `Shapefile::DBF_TYPE_CHAR`
    - `Shapefile::DBF_TYPE_DATE`
    - `Shapefile::DBF_TYPE_LOGICAL`
    - `Shapefile::DBF_TYPE_MEMO`
    - `Shapefile::DBF_TYPE_NUMERIC`
    - `Shapefile::DBF_TYPE_FLOAT`
- Error types constants:
    - `Shapefile::ERR_UNDEFINED`
    - `Shapefile::ERR_FILE_MISSING`
    - `Shapefile::ERR_FILE_EXISTS`
    - `Shapefile::ERR_FILE_INVALID_RESOURCE`
    - `Shapefile::ERR_FILE_OPEN`
    - `Shapefile::ERR_FILE_READING`
    - `Shapefile::ERR_FILE_WRITING`
    - `Shapefile::ERR_SHP_TYPE_NOT_SUPPORTED`
    - `Shapefile::ERR_SHP_TYPE_NOT_SET`
    - `Shapefile::ERR_SHP_TYPE_ALREADY_SET`
    - `Shapefile::ERR_SHP_GEOMETRY_TYPE_NOT_COMPATIBLE`
    - `Shapefile::ERR_SHP_MISMATCHED_BBOX`
    - `Shapefile::ERR_SHP_FILE_ALREADY_INITIALIZED`
    - `Shapefile::ERR_SHP_WRONG_RECORD_TYPE`
    - `Shapefile::ERR_DBF_FILE_NOT_VALID`
    - `Shapefile::ERR_DBF_MISMATCHED_FILE`
    - `Shapefile::ERR_DBF_EOF_REACHED`
    - `Shapefile::ERR_DBF_MAX_FIELD_COUNT_REACHED`
    - `Shapefile::ERR_DBF_FIELD_NAME_NOT_UNIQUE`
    - `Shapefile::ERR_DBF_FIELD_NAME_NOT_VALID`
    - `Shapefile::ERR_DBF_FIELD_TYPE_NOT_VALID`
    - `Shapefile::ERR_DBF_FIELD_SIZE_NOT_VALID`
    - `Shapefile::ERR_DBF_FIELD_DECIMALS_NOT_VALID`
    - `Shapefile::ERR_DBF_CHARSET_CONVERSION`
    - `Shapefile::ERR_DBT_EOF_REACHED`
    - `Shapefile::ERR_GEOM_NOT_EMPTY`
    - `Shapefile::ERR_GEOM_COORD_VALUE_NOT_VALID`
    - `Shapefile::ERR_GEOM_MISMATCHED_DIMENSIONS`
    - `Shapefile::ERR_GEOM_MISMATCHED_BBOX`
    - `Shapefile::ERR_GEOM_MISSING_FIELD`
    - `Shapefile::ERR_GEOM_POINT_NOT_VALID`
    - `Shapefile::ERR_GEOM_POLYGON_OPEN_RING`
    - `Shapefile::ERR_GEOM_POLYGON_AREA_TOO_SMALL`
    - `Shapefile::ERR_GEOM_POLYGON_NOT_VALID`
    - `Shapefile::ERR_INPUT_RECORD_NOT_FOUND`
    - `Shapefile::ERR_INPUT_FIELD_NOT_FOUND`
    - `Shapefile::ERR_INPUT_GEOMETRY_TYPE_NOT_VALID`
    - `Shapefile::ERR_INPUT_GEOMETRY_INDEX_NOT_VALID`
    - `Shapefile::ERR_INPUT_ARRAY_NOT_VALID`
    - `Shapefile::ERR_INPUT_WKT_NOT_VALID`
    - `Shapefile::ERR_INPUT_GEOJSON_NOT_VALID`
    - `Shapefile::ERR_INPUT_NUMERIC_VALUE_OVERFLOW`

### Changed
- Folder structure under `src/` reflects namespaces hierarchy
- Namespace and class names case normalized
- Bitwise constructor flags replaced by associative array
- Default output polygons orientation is now opposite to ESRI Shapefile specs and compliant to OGC Simple Features
- Use of `iconv()` instead of `utf8_encode()` for charset conversion
- `ShapefileException::getErrorType()` method returns one of `Shapefile::ERR_*` constant values
- `ShapefileReader::fetchRecord()` method replaces `ShapefileReader::getRecord()` and returns an object
- Order of bounding boxes associative arrays elements

### Fixed
- Stricter invalid date format detection
- Logical (`bool`) not initialized values (`null`) detection in *DBF* files

### Removed
- `ShapefileReader` public methods:
  - `setDefaultGeometryFormat()`
  - `readRecord()`
- `ShapefileReader` protected method `init()`
- `Shapefile` constants:
    - `Shapefile::FLAG_SUPPRESS_Z`
    - `Shapefile::FLAG_SUPPRESS_M`
    - `Shapefile::GEOMETRY_ARRAY`
    - `Shapefile::GEOMETRY_WKT`
    - `Shapefile::GEOMETRY_GEOJSON_GEOMETRY`
    - `Shapefile::GEOMETRY_GEOJSON_FEATURE`
    - `Shapefile::GEOMETRY_BOTH`
- `Shapefile` numeric error codes



## v2.4.3 - 2018-04-07
### Fixed
- Bug in binary reading function causing issues with *DBF* fields size



## v2.4.2 - 2017-12-06
### Fixed
- Reversed the orientation of inner and outer rings in GeoJSON output of Polygons and MultiPolygons, complying to section 3.1.6 of RFC 7946



## v2.4.1 - 2017-11-30
### Fixed
- Fixed *composer.json* file. Since version 2.4.0 the library requires PHP 5.4+



## v2.4.0 - 2017-11-20
### Added
- Public method `setDefaultGeometryFormat()`. It sets the default format for future calls to `getRecord()` and use with the Iterator interface (foreach loop)
- `GEOMETRY_GEOJSON_GEOMETRY` and `GEOMETRY_GEOJSON_FEATURE` formats for `getRecord()` method

### Changed
- Geometry return format types for `getRecord()` can be combined using *bitwise Or* operator `|`
- Default geometry return format for `getRecord()` method changed to `ShapeFile::GEOMETRY_ARRAY`

### Deprecated
- `GEOMETRY_BOTH` geometry return format for `getRecord()` method - Please update your code still relying on it



## v2.3.0 - 2017-09-14
### Added
- Protected method `init()` allows the main `ShapeFile` class to be easily extended using a custom constructor

### Fixed
- Some minor code embellishments to better comply to PSR-2



## v2.2.0 - 2016-11-23
### Added
- Capability to randomly access the shapefile records
- Implements the PHP Iterator interface
- Public method `getTotRecords()`. It provides the number of records in the shapefile
- Public method `setCurrentRecord()`. It sets the current record pointer
- Public method `getCurrentRecord()`. It gets the current record pointer
- Error code 91: RECORD_INDEX_NOT_VALID

### Changed
- Class constructor: *SHX* file is now required

### Fixed
- Handles eventual useless random bytes between records in the *SHP* file (using the *SHX* file for the correct offsets)



## v2.1.0 - 2016-11-17
### Added
- Public method `getDBFFields()`. It provides the fields definition in the *DBF* file



## v2.0.1 - 2016-11-10
### Fixed
- PHP 7 Uniform Variable Syntax bugfix in `getRecord()` method



## v2.0.0 - 2016-11-01
### Added
- Support for Z and M shapes
- Native *DBF* reading capabilities (no dbase functions or external libraries required anymore)
- `FLAG_SUPPRESS_Z` and `FLAG_SUPPRESS_M` flags for ShapeFile constructor
- `GEOMETRY_BOTH` format for `getRecord()` method
- `ShapeFile` namespace and `ShapeFileAutoloader` class
- *DBF* error codes `41` and `42`
- CHANGELOG file

### Changed
- Code is now php-fig PSR-1, PSR-2 and PSR-4 compliant
- PHP 7 compatible
- All strings read from *DBF* are returned already encoded in utf-8

### Removed
- PHP XBase library dependency as dbase fallback 

### Fixed
- It works on big-endian machines



## v1.1 - 2016-03-31
### Added
- Public method `getPRJ()`. It provides the raw WKT from the .prj file if present
- Composer.json

### Changed
- Class constructor (you must update your code to explicitly pass the .dbf file path)
- Updated error codes

### Fixed
- Invalid polygons handling



## v1.0 - 2014-11-13
*First public release*
