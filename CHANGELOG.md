# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) 
and this project adheres to [Semantic Versioning](http://semver.org/).


## v2.4.3 - 2018-04-07
### Fixed
- Bug in binary reading function causing issues with DBF fields size



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
- Some minor code abbelishments to better comply to PSR-2



## v2.2.0 - 2016-11-23
### Added
- Capability to randomly access the shapefile records
- Implements the PHP Iterator interface
- Public method `getTotRecords()`. It provides the number of records in the shapefile
- Public method `setCurrentRecord()`. It sets the current record pointer
- Public method `getCurrentRecord()`. It gets the current record pointer
- Error code 91: RECORD_INDEX_NOT_VALID

### Changed
- Class constructor: SHX file is now required

### Fixed
- Handles eventual useless random bytes between records in the SHP file (using the SHX file for the correct offsets)



## v2.1.0 - 2016-11-17
### Added
- Public method `getDBFFields()`. It provides the fields definition in the DBF file



## v2.0.1 - 2016-11-10
### Fixed
- PHP 7 Uniform Variable Syntax bugfix in `getRecord()` method



## v2.0.0 - 2016-11-01
### Added
- Support for Z and M shapes
- Native DBF reading capabilities (no dbase functions or external libraries required anymore)
- `FLAG_SUPPRESS_Z` and `FLAG_SUPPRESS_M` flags for ShapeFile constructor
- `GEOMETRY_BOTH` format for `getRecord()` method
- `ShapeFile` namespace and `ShapeFileAutoloader` class
- DBF error codes `41` and `42`
- CHANGELOG file

### Changed
- Code is now php-fig PSR-1, PSR-2 and PSR-4 compliant
- PHP 7 compatible
- All strings read from DBF are returned already encoded in utf-8

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
