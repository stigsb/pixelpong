<?php

namespace stigsb\pixelpong\frame;

use PHPUnit\Framework\TestCase;

class JsonFrameEncoderTest extends TestCase
{
    const TEST_HEIGHT = 3;
    const TEST_WIDTH = 7;

    /** @var FrameBuffer|\PHPUnit\Framework\MockObject\MockObject */
    private $frameBuffer;

    /** @var JsonFrameEncoder */
    private $encoder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->frameBuffer = $this->createMock(FrameBuffer::class);
        $this->frameBuffer->method('getHeight')->willReturn(self::TEST_HEIGHT);
        $this->frameBuffer->method('getWidth')->willReturn(self::TEST_WIDTH);
        $this->encoder = new JsonFrameEncoder($this->frameBuffer);
    }

    public function testEncode()
    {
        $num_pixels = self::TEST_WIDTH * self::TEST_HEIGHT;
        $frame = array_fill(0, $num_pixels, 0);
        $set_pixels = [2, 3, 9, 10];
        $expected = ['frameDelta' => []];
        foreach ($set_pixels as $sp) {
            $frame[$sp] = 1;
            $expected['frameDelta'][$sp] = JsonFrameEncoder::COLOR_FG;
        }
        $this->assertEquals(json_encode($expected), $this->encoder->encodeFrame($frame));
    }

}
