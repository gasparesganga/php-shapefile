<?php

use PHPUnit\Framework\TestCase;
use ShapeFile\LocalFile;
use ShapeFile\ShapeFile;

class ShapeFilePointTest extends TestCase
{
    protected function setUp()
    {
        $path = __DIR__.'/Fixtures/demo_formulierenPoint';
        $this->shp = new LocalFile($path.'.shp');
        $this->dbf = new LocalFile($path.'.dbf');
        $this->shx = new LocalFile($path.'.shx');
        $this->prj = new LocalFile($path.'.prj');
    }

    public function testGetBoundingBox()
    {
        $shpfile = new ShapeFile($this->shp, $this->shx, $this->dbf, $this->prj);
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
        $shpfile = new ShapeFile($this->shp, $this->shx, $this->dbf, $this->prj);
        while ($record = $shpfile->getRecord(ShapeFile::GEOMETRY_BOTH)) {
            $this->assertArrayHasKey('shp', $record);
            $this->assertArrayHasKey('wkt', $record['shp']);
            $this->assertStringStartsWith('POINT', $record['shp']['wkt']);
            $this->assertArrayHasKey('dbf', $record);
            $this->assertArrayHasKey('naam', $record['dbf']);
        }
    }
}
