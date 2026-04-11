<?php


namespace stigsb\pixelpong\server;


use stigsb\pixelpong\frame\FrameEncoder;

class ActivePlayerPlayerConnection implements PlayerConnection
{
    /** @var FrameEncoder */
    private $frameEncoder;

    /** @var bool */
    private $inputEnabled;

    /** @var bool */
    private $outputEnabled;

    public function __construct(FrameEncoder $frameEncoder)
    {
        $this->frameEncoder = $frameEncoder;
        $this->inputEnabled = false;
        $this->outputEnabled = false;
    }

    /**
     * @return FrameEncoder
     */
    public function getFrameEncoder()
    {
        return $this->frameEncoder;
    }

    public function setInputEnabled($enabled)
    {
        $this->inputEnabled = $enabled;
    }

    public function setOutputEnabled($enabled)
    {
        $this->outputEnabled = $enabled;
    }

    public function isInputEnabled()
    {
        return $this->inputEnabled;
    }

    public function isOutputEnabled()
    {
        return $this->outputEnabled;
    }

}
