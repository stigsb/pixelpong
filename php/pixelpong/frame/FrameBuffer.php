<?php

namespace stigsb\pixelpong\frame;

use stigsb\pixelpong\bitmap\Bitmap;

interface FrameBuffer
{
    public function getWidth();

    public function getHeight();

    public function getPixel($x, $y);

    /**
     * @param $x
     * @param $y
     * @param $color
     */
    public function setPixel($x, $y, $color);

    /**
     * @return array  a fixed-size array, the index of each pixel being (y*width)+x
     */
    public function getFrame();

    /**
     * @return array  a fixed-size array, the index of each pixel being (y*width)+x
     */
    public function getAndSwitchFrame();

    /**
     * Set the background frame, which is the one the frame buffer reverts to for every
     * game loop.
     *
     * @param array $frame
     * @return mixed
     */
    public function setBackgroundFrame(array $frame);

    /**
     * @param Bitmap $bitmap
     * @param int $x
     * @param int $y
     */
    public function drawBitmapAt(Bitmap $bitmap, $x, $y);
}
