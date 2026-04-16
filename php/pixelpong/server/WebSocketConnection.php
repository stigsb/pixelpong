<?php

namespace stigsb\pixelpong\server;

use Ratchet\RFC6455\Messaging\Frame;
use React\Stream\ThroughStream;

class WebSocketConnection
{
    private ThroughStream $out;
    private bool $closed = false;

    public function __construct(ThroughStream $out)
    {
        $this->out = $out;
    }

    public function send(string $text): void
    {
        if ($this->closed) {
            return;
        }
        $frame = new Frame($text, true, Frame::OP_TEXT);
        $this->out->write($frame->getContents());
    }

    public function close(int $code = 1000, string $reason = ''): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        $payload = pack('n', $code) . $reason;
        $frame = new Frame($payload, true, Frame::OP_CLOSE);
        $this->out->write($frame->getContents());
        $this->out->end();
    }
}
