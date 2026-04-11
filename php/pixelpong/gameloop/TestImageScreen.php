<?php


namespace stigsb\pixelpong\gameloop;


use Psr\Container\ContainerInterface;
use stigsb\pixelpong\frame\FrameBuffer;
use stigsb\pixelpong\server\Event;
use stigsb\pixelpong\server\GameServer;

class TestImageScreen extends BaseGameLoop
{
    public function __construct(FrameBuffer $frameBuffer, ContainerInterface $container)
    {
        parent::__construct($frameBuffer, $container);
        $this->background = $this->bitmapLoader->loadBitmap('test_image');
    }
    /**
     * An input event occurs (joystick action).
     * @param Event $event
     */
    public function onEvent(Event $event)
    {
        if ($event->eventType == Event::JOY_BUTTON_1 && $event->value == Event::BUTTON_NEUTRAL) {
            $this->container->get(GameServer::class)->switchToGameLoop(
                $this->container->get(PressStartToPlayGameLoop::class)
            );
        }
    }

}