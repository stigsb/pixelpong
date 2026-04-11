<?php

namespace stigsb\pixelpong\gameloop;

use Psr\Container\ContainerInterface;
use stigsb\pixelpong\frame\FrameBuffer;
use stigsb\pixelpong\server\Event;
use stigsb\pixelpong\server\GameServer;

class PressStartToPlayGameLoop extends BaseGameLoop
{
    /** @var array */
    private $pressStartFrame;

    /** @var array */
    private $toPlayFrame;

    /** @var int */
    private $enterTime;

    /** @var int */
    private $previousTime;

    public function __construct(FrameBuffer $frameBuffer, ContainerInterface $container)
    {
        parent::__construct($frameBuffer, $container);
        $this->pressStartFrame = $this->bitmapLoader->loadBitmap('press_start')->getPixels();
        $this->toPlayFrame = $this->bitmapLoader->loadBitmap('to_play')->getPixels();
    }

    /**
     * This method is called when the game enters this game loop.
     * This is where you would replace the default frame among other things.
     */
    public function onEnter()
    {
        $this->frameBuffer->setBackgroundFrame($this->pressStartFrame);
        $this->previousTime = 0;
        $this->enterTime = time();
    }

    public function onFrameUpdate()
    {
        $elapsed = time() - $this->enterTime;
        if ($elapsed > $this->previousTime) {
            switch ($elapsed % 4) {
                case 0:
                    $this->frameBuffer->setBackgroundFrame($this->pressStartFrame);
                    break;
                case 2:
                    $this->frameBuffer->setBackgroundFrame($this->toPlayFrame);
                    break;
            }
        }
        $this->previousTime = $elapsed;
    }

    /**
     * An input event occurs (joystick action).
     * @param Event $event
     */
    public function onEvent(Event $event)
    {
        if ($event->eventType == Event::JOY_BUTTON_1 && $event->value == Event::BUTTON_NEUTRAL) {
            $this->container->get(GameServer::class)->switchToGameLoop(
                $this->container->get(MainGameLoop::class)
            );
        }
    }

}
