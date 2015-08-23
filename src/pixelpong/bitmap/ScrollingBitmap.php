<?php

namespace stigsb\pixelpong\bitmap;

use stigsb\pixelpong\server\Color;

class ScrollingBitmap implements Bitmap
{
    /** @var Bitmap */
    protected $bitmap;

    /** @var int */
    protected $width;

    /** @var int */
    protected $height;

    /** @var int */
    protected $xOffset;

    /** @var int */
    protected $yOffset;

    /** @var \SplFixedArray */
    protected $blankPixels;

    public function __construct(Bitmap $bitmap, $width, $height, $xOffset=0, $yOffset=0)
    {
        $this->bitmap = $bitmap;
        $this->width = $width;
        $this->height = $height;
        $this->xOffset = $xOffset;
        $this->yOffset = $yOffset;
        $this->blankPixels = \SplFixedArray::fromArray(array_fill(0, $width * $height, Color::TRANSPARENT));
    }

    /**
     * @return Bitmap
     */
    public function getOriginalBitmap()
    {
        return $this->bitmap;
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
     * @return \SplFixedArray
     */
    public function getPixels()
    {
        $origw = $this->bitmap->getWidth();
        $origh = $this->bitmap->getHeight();
        $origpixels = $this->bitmap->getPixels();
        $pixels = clone $this->blankPixels;
        $maxx = min($this->width, $origw - $this->xOffset);
        $maxy = min($this->height, $origh - $this->yOffset);
        for ($y = 0; $y < $maxy; ++$y) {
            $oy = $this->yOffset = $y;
            for ($x = 0; $x < $maxx; ++$x) {
                $ox = $this->xOffset + $x;
                $pixels[($y * $this->width) + $x] = $origpixels[($oy * $origw) + $ox];
            }
        }
        return $pixels;
    }


    public function scrollTo($x, $y) {
        $this->xOffset = $x;
        $this->yOffset = $y;
    }

}
