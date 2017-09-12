<?php

namespace ShapeFile;

interface FileInterface
{
    /**
     * @return resource file stream
     */
    public function readStream();

    /**
     * @return int file size
     */
    public function getSize();
}