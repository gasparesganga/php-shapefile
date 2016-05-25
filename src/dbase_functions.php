<?php
/***************************************************************************************************
This is a name conversion module to Erwin Kooi's brilliant PHPXBase lib
which subsitutes PHP dBase functions for those who haven't enabled it in their modern PHP install.
You can download it here:
http://www.phpclasses.org/package/2673-PHP-Access-dbf-foxpro-files-without-PHP-ext-.html

This file is intended to expose his functions with a "dbase_" prefix instead of "xbase_" 
as found on PHP own dBase functions.
Please refer to PHP Manual for documentation: http://php.net/manual/en/ref.dbase.php
****************************************************************************************************/

// =================================================================================================
require_once(dirname(__FILE__).'/phpxbase/api_conversion.php');
// =================================================================================================

function dbase_add_record($dbase_identifier, $record) {
    return xbase_add_record($dbase_identifier, $record);
}
function dbase_close($dbase_identifier) {
    return xbase_close($dbase_identifier);
}
function dbase_create($filename, $fields) {
    return xbase_create($filename, $fields);
}
function dbase_delete_record($dbase_identifier, $record_number) {
    return xbase_delete_record($dbase_identifier, $record_number);
}
function dbase_get_header_info($dbase_identifier) {
    return xbase_get_header_info($dbase_identifier);
}
function dbase_get_record_with_names($dbase_identifier, $record_number) {
    return xbase_get_record_with_names($dbase_identifier, $record_number);
}
function dbase_get_record($dbase_identifier, $record_number) {
    return xbase_get_record($dbase_identifier, $record_number);
}
function dbase_numfields($dbase_identifier) {
    return xbase_numfields($dbase_identifier);
}
function dbase_numrecords($dbase_identifier) {
    return xbase_numrecords($dbase_identifier);
}
function dbase_open($filename, $mode = 0) {
    return xbase_open($filename, $mode);
}
function dbase_pack($dbase_identifier) {
    return xbase_pack($dbase_identifier);
}
function dbase_replace_record($dbase_identifier, $record, $record_number) {
    return xbase_replace_record($dbase_identifier, $record, $record_number);
}
?>