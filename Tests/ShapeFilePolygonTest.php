<?php


use PHPUnit\Framework\TestCase;
use ShapeFile\ShapeFile;

class ShapeFilePolygonTest extends TestCase
{
    public function testGetBoundingBox()
    {
        $shpfile = new ShapeFile(__DIR__.'/Fixtures/demo_formulierenPolygon');
        $bbox = $shpfile->getBoundingBox();
        $this->assertEquals([
            'xmin' => 103083.42,
            'xmax' => 138715.9,
            'ymin' => 470103.74,
            'ymax' => 495535.64,
        ], $bbox);
    }

    public function testGetRecord()
    {
        $shpfile = new ShapeFile(__DIR__.'/Fixtures/demo_formulierenPolygon');
        while ($record = $shpfile->getRecord(ShapeFile::GEOMETRY_BOTH)) {
            $this->assertArrayHasKey('shp', $record);
            $this->assertArrayHasKey('wkt', $record['shp']);
            $this->assertStringStartsWith('POLYGON', $record['shp']['wkt']);
            $this->assertArrayHasKey('dbf', $record);
            $this->assertArrayHasKey('naam', $record['dbf']);
        }
    }

    public function testGetDBFFields()
    {
        $shpfile = new ShapeFile(__DIR__.'/Fixtures/demo_formulierenPolygon');
        $fields = $shpfile->getDBFFields();
        $this->assertInternalType('array', $shpfile->getDBFFields());
        $this->assertArrayHasKey('name', $fields['0']);
    }
}
