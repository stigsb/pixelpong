<?php

namespace stigsb\pixelpong\frame;

use stigsb\pixelpong\server\Color;

class JsonFrameEncoder implements FrameEncoder
{
    const COLOR_BG = Color::BLACK;
    const COLOR_FG = Color::WHITE;

    /** @var int */
    private $width;

    /** @var int */
    private $height;

    /** @var array */
    private $previousFrame;

    /** @var array */
    private $blankEncodedFrame;

    public function __construct(FrameBuffer $frameBuffer)
    {
        $this->width = $frameBuffer->getWidth();
        $this->height = $frameBuffer->getHeight();
        $size = $this->width * $this->height;
        $this->blankEncodedFrame = array_fill(0, $size, self::COLOR_BG);
        $this->previousFrame = array_fill(0, $size, self::COLOR_BG);
    }

    /**
     * @param array $frame
     * @return string
     */
    public function encodeFrame(array $frame)
    {
        $pixels = [];
        $diff = array_diff_assoc($frame, $this->previousFrame);
        $this->previousFrame = $frame;
        if (count($diff) == 0) {
            return null;
        }
        if (count($diff) < (count($frame) / 3)) {
            // send out a diff if less than ~1/3 of the pixels have changed
//            printf("Sending delta, %d of %d pixels changed\n", count($diff), count($frame));
            return json_encode(['frameDelta' => $diff]);
        }
//        printf("Sending full frame, %d of %d pixels changed\n", count($diff), count($frame));
        foreach ($frame as $ix => $color) {
            if ($color !== self::PIXEL_BG) {
                $pixels[(string)$ix] = $color;
            }
        }
        return json_encode(['frame' => $pixels]);
    }

    /**
     * @param FrameBuffer $frameBuffer
     * @return string
     */
    public function encodeFrameInfo(FrameBuffer $frameBuffer)
    {
        return json_encode([
            'frameInfo' => [
                'width' => $frameBuffer->getWidth(),
                'height' => $frameBuffer->getHeight(),
                'palette' => Color::getPalette(),
            ]
        ]);
    }

}
