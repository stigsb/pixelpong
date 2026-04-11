<?php

namespace stigsb\pixelpong\bitmap;

class SimpleBitmap implements Bitmap
{
    /** @var int */
    protected $width;

    /** @var int */
    protected $height;

    /** @var array */
    protected $pixels;

    /**
     * @param int $width
     * @param int $height
     * @param array $pixels
     */
    public function __construct($width, $height, array $pixels)
    {
        $this->width = $width;
        $this->height = $height;
        $this->pixels = $pixels;
    }

    /**
     * @return int
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * @return array
     */
    public function getPixels()
    {
        return $this->pixels;
    }

}

