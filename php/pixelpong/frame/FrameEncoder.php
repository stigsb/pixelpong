<?php

namespace stigsb\pixelpong\frame;

interface FrameEncoder
{
    const PIXEL_BG = 0;

    /**
     * @param array $frame
     * @return string
     */
    public function encodeFrame(array $frame);

    /**
     * @param FrameBuffer $frameBuffer
     * @return string
     */
    public function encodeFrameInfo(FrameBuffer $frameBuffer);
}
