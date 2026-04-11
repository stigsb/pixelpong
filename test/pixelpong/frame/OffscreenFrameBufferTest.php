<?php

namespace stigsb\pixelpong\frame;

use PHPUnit\Framework\TestCase;

class OffscreenFrameBufferTest extends TestCase
{
    const WIDTH = 7;
    const HEIGHT = 3;

    /** @var OffscreenFrameBuffer */
    private $ofb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ofb = new OffscreenFrameBuffer(self::WIDTH, self::HEIGHT);
    }

    public function testConstructor()
    {
        $empty = array_fill(0, self::WIDTH * self::HEIGHT, 0);
        $this->assertEquals($empty, $this->ofb->getFrame());
        $this->assertEquals(self::WIDTH, $this->ofb->getWidth());
        $this->assertEquals(self::HEIGHT, $this->ofb->getHeight());
    }

    public function testSetAndGetPixel()
    {
        for ($y = 0; $y < self::HEIGHT; ++$y) {
            for ($x = 0; $x < self::WIDTH; ++$x) {
                $this->assertEquals(0, $this->ofb->getPixel($x, $y));
            }
        }
        $this->ofb->setPixel(1, 2, 1);
        $this->assertEquals(1, $this->ofb->getPixel(1, 2));
        $this->assertEquals(0, $this->ofb->getPixel(2, 1));
    }

    public function testGetPixelOutOfBounds()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$x or $y out of bounds');
        $this->ofb->getPixel(10, 10);
    }

    public function testSetPixelOutOfBounds()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$x or $y out of bounds');
        $this->ofb->setPixel(10, 10, 0);
    }

    public function testGetAndSwitchFrame()
    {
        $this->ofb->setPixel(0, 0, 1);
        $this->assertEquals(1, $this->ofb->getAndSwitchFrame()[0]);
        $this->assertEquals(0, $this->ofb->getFrame()[0]);
    }

}
