<?php


use PHPUnit\Framework\TestCase;
use ShapeFile\ShapeFile;
use ShapeFile\LocalFile;

class ShapeFilePolygonTest extends TestCase
{
    protected function setUp()
    {
        $path = __DIR__.'/Fixtures/demo_formulierenPolygon';
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
            'xmin' => 103083.42,
            'xmax' => 138715.9,
            'ymin' => 470103.74,
            'ymax' => 495535.64,
        ], $bbox);
    }

    public function testGetRecord()
    {
        $shpfile = new ShapeFile($this->shp, $this->shx, $this->dbf, $this->prj);
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
        $shpfile = new ShapeFile($this->shp, $this->shx, $this->dbf, $this->prj);
        $fields = $shpfile->getDBFFields();
        $this->assertInternalType('array', $shpfile->getDBFFields());
        $this->assertArrayHasKey('name', $fields['0']);
    }
}
