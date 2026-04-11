<?php

namespace stigsb\pixelpong\bitmap;

interface Bitmap
{
    /**
     * @return int
     */
    public function getWidth();

    /**
     * @return int
     */
    public function getHeight();

    /**
     * @return array
     */
    public function getPixels();
}
