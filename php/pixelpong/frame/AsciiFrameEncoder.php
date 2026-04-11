<?php

namespace stigsb\pixelpong\frame;

use stigsb\pixelpong\bitmap\BitmapLoader;
use stigsb\pixelpong\server\Color;

class AsciiFrameEncoder implements FrameEncoder
{
    const ENCODED_PIXEL_OFF = '.';
    const ENCODED_PIXEL_ON  = '#';

    /** @var int */
    private $width;

    /** @var int */
    private $height;

    /** @var array */
    private $colorCharMap;

    /** @var string */
    private $blankEncodedFrame;

    public function __construct(FrameBuffer $frameBuffer)
    {
        $this->width = $frameBuffer->getWidth();
        $this->height = $frameBuffer->getHeight();
        // need room for each pixel as a char, and newlines between them
        $encodedSize = ($this->height * ($this->width + 1)) - 1;
        $this->blankEncodedFrame = str_repeat(self::ENCODED_PIXEL_OFF, $encodedSize);
        for ($i = $this->width; $i < $encodedSize; $i += ($this->width + 1)) {
            $this->blankEncodedFrame[$i] = "\n";
        }
        $this->colorCharMap = array_flip(BitmapLoader::$colorMap);
    }

    public function encodeFrame(array $frame)
    {
        $pixels = $this->blankEncodedFrame;
        for ($y = 0; $y < $this->height; ++$y) {
            for ($x = 0; $x < $this->width; ++$x) {
                $color = $frame[($this->width * $y) + $x];
                if ($color == Color::TRANSPARENT) {
                    continue;
                }
                $pixels[(($this->width + 1) * $y) + $x] = isset($this->colorCharMap[$color]) ? $this->colorCharMap[$color] : ' ';
            }
        }
        return $pixels;
    }

    /**
     * @param FrameBuffer $frameBuffer
     * @return string
     */
    public function encodeFrameInfo(FrameBuffer $frameBuffer)
    {
        return '';
    }

}
