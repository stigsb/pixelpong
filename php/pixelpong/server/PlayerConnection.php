<?php

namespace stigsb\pixelpong\server;

use stigsb\pixelpong\frame\FrameEncoder;

interface PlayerConnection
{
    public function getFrameEncoder(): FrameEncoder;

    public function setInputEnabled(bool $enabled): void;

    public function setOutputEnabled(bool $enabled): void;

    public function isInputEnabled(): bool;

    public function isOutputEnabled(): bool;
}
