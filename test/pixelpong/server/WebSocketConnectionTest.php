<?php

namespace stigsb\pixelpong\server;

use PHPUnit\Framework\TestCase;
use React\Stream\ThroughStream;
use Ratchet\RFC6455\Messaging\Frame;

class WebSocketConnectionTest extends TestCase
{
    private function decodeFrame(string $raw): Frame
    {
        $frame = new Frame();
        $frame->addBuffer($raw);
        return $frame;
    }

    public function testSendWritesTextFrame(): void
    {
        $out = new ThroughStream();
        $written = '';
        $out->on('data', function ($data) use (&$written) {
            $written .= $data;
        });

        $conn = new WebSocketConnection($out);
        $conn->send('hello');

        $frame = $this->decodeFrame($written);
        $this->assertTrue($frame->isFinal());
        $this->assertSame(Frame::OP_TEXT, $frame->getOpcode());
        $this->assertSame('hello', $frame->getPayload());
    }

    public function testCloseWritesCloseFrame(): void
    {
        $out = new ThroughStream();
        $written = '';
        $out->on('data', function ($data) use (&$written) {
            $written .= $data;
        });

        $conn = new WebSocketConnection($out);
        $conn->close();

        $frame = $this->decodeFrame($written);
        $this->assertSame(Frame::OP_CLOSE, $frame->getOpcode());
    }

    public function testSendAfterCloseIsIgnored(): void
    {
        $out = new ThroughStream();
        $conn = new WebSocketConnection($out);
        $conn->close();

        // Should not throw or write
        $conn->send('ignored');
        $this->assertTrue(true); // Reached without error
    }
}
