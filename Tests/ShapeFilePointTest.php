<?php

use PHPUnit\Framework\TestCase;
use ShapeFile\ShapeFile;

class ShapeFilePointTest extends TestCase
{
    public function testGetBoundingBox()
    {
        $shpfile = new ShapeFile(__DIR__.'/Fixtures/demo_formulierenPoint');
        $bbox = $shpfile->getBoundingBox();
        $this->assertEquals([
            'xmin' => 111620.86,
            'xmax' => 175371.98,
            'ymin' => 442954.94,
            'ymax' => 459839,
        ], $bbox);
    }

    public function testGetRecord()
    {
        $shpfile = new ShapeFile(__DIR__.'/Fixtures/demo_formulierenPoint');
        while ($record = $shpfile->getRecord(ShapeFile::GEOMETRY_BOTH)) {
            $this->assertArrayHasKey('shp', $record);
            $this->assertArrayHasKey('wkt', $record['shp']);
            $this->assertStringStartsWith('POINT', $record['shp']['wkt']);
            $this->assertArrayHasKey('dbf', $record);
            $this->assertArrayHasKey('naam', $record['dbf']);
        }
    }
}
