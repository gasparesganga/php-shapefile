<?php

namespace ShapeFile;

class LocalFile implements FileInterface
{
    /**
     * [$path description].
     *
     * @var string
     */
    private $path;

    public function __construct($path)
    {
        $this->path = $path;
    }

    public function readStream()
    {
        if (!(is_readable($this->path) && is_file($this->path))) {
            $this->throwException('FILE_EXISTS', $this->path);
        }
        $handle = fopen($this->path, 'rb');
        if (!$handle) {
            throw new Exception('Could no open file');
        }

        return $handle;
    }

    public function getSize()
    {
        return filesize($this->path);
    }

    public function read()
    {
        return file_get_contents($this->path);
    }
}
