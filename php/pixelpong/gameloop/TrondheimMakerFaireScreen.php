<?php

namespace stigsb\pixelpong\gameloop;

use stigsb\pixelpong\bitmap\BitmapLoader;
use stigsb\pixelpong\frame\FrameBuffer;
use stigsb\pixelpong\server\Event;

class TrondheimMakerFaireScreen implements GameLoop
{
    /** @var FrameBuffer */
    private $frameBuffer;

    /** @var array[] */
    private $frames;

    /** @var int */
    private $previousTime;

    /** @var int */
    private $currentFrameIndex;

    public function __construct(FrameBuffer $frameBuffer, BitmapLoader $bitmapLoader)
    {
        $this->frameBuffer = $frameBuffer;
        $this->frames = [];
        foreach (['trondheim', 'maker', 'faire'] as $bitmapName) {
            $bitmap = $bitmapLoader->loadBitmap($bitmapName);
            $this->frames[] = $bitmap->getPixels();
        }
    }

    /**
     * This method is called when the game enters this game loop.
     * This is where you would replace the default frame among other things.
     */
    public function onEnter()
    {
        $this->currentFrameIndex = 0;
        $this->frameBuffer->setBackgroundFrame($this->frames[0]);
        $this->previousTime = time();
    }

    public function onFrameUpdate()
    {
        $now = time();
        if ($now > $this->previousTime) {
            $this->currentFrameIndex = ($this->currentFrameIndex + 1) % count($this->frames);
            $this->frameBuffer->setBackgroundFrame($this->frames[$this->currentFrameIndex]);
        }
        $this->previousTime = $now;
    }

    /**
     * An input event occurs (joystick action).
     * @param Event $event
     */
    public function onEvent(Event $event)
    {
        // TODO: Implement onEvent() method.
    }

}