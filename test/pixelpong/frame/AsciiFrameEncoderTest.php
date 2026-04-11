<?php

namespace stigsb\pixelpong\frame;

use PHPUnit\Framework\TestCase;

class AsciiFrameEncoderTest extends TestCase
{
    const TEST_HEIGHT = 3;
    const TEST_WIDTH = 7;
    const TEST_BLANK_ENCODED = "0000000\n0000000\n0000000";

    /** @var FrameBuffer|\PHPUnit\Framework\MockObject\MockObject */
    private $frameBuffer;

    /** @var AsciiFrameEncoder */
    private $encoder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->frameBuffer = $this->createMock(FrameBuffer::class);
        $this->frameBuffer->method('getHeight')->willReturn(self::TEST_HEIGHT);
        $this->frameBuffer->method('getWidth')->willReturn(self::TEST_WIDTH);
        $this->encoder = new AsciiFrameEncoder($this->frameBuffer);
    }

    public function testEncodeFrame()
    {
        $num_pixels = self::TEST_WIDTH * self::TEST_HEIGHT;
        $frame = array_fill(0, $num_pixels, 0);
        $set_pixels = [2 => 2, 3 => 3, 9 => 10, 10 => 11];
        $expected = self::TEST_BLANK_ENCODED;
        foreach ($set_pixels as $sp => $ep) {
            $frame[$sp] = 1;
            $expected[$ep] = '1';
        }
        $this->assertEquals($expected, $this->encoder->encodeFrame($frame));
    }

    public function testEncodeFrameInfo()
    {
        $this->assertEquals('', $this->encoder->encodeFrameInfo($this->frameBuffer));
    }

}
