# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) 
and this project adheres to [Semantic Versioning](http://semver.org/).


## [2.0.0] - 2016-11-01
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


## [1.1] - 2016-03-31
### Added
- Public method `getPRJ()`. It provides the raw WKT from the .prj file if present
- Composer.json

### Changed
- Class constructor (you must update your code to explicitly pass the .dbf file path)
- Updated error codes

### Fixed
- Invalid polygons handling


## [1.0] - 2014-11-13
*First public release*
