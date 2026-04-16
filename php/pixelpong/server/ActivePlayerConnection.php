<?php

namespace stigsb\pixelpong\server;

use stigsb\pixelpong\frame\FrameEncoder;

class ActivePlayerConnection implements PlayerConnection
{
    private FrameEncoder $frameEncoder;
    private bool $inputEnabled = false;
    private bool $outputEnabled = false;

    public function __construct(FrameEncoder $frameEncoder)
    {
        $this->frameEncoder = $frameEncoder;
    }

    public function getFrameEncoder(): FrameEncoder
    {
        return $this->frameEncoder;
    }

    public function setInputEnabled(bool $enabled): void
    {
        $this->inputEnabled = $enabled;
    }

    public function setOutputEnabled(bool $enabled): void
    {
        $this->outputEnabled = $enabled;
    }

    public function isInputEnabled(): bool
    {
        return $this->inputEnabled;
    }

    public function isOutputEnabled(): bool
    {
        return $this->outputEnabled;
    }
}
