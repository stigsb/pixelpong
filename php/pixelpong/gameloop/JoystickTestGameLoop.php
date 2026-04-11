<?php

namespace stigsb\pixelpong\gameloop;

use Psr\Container\ContainerInterface;
use stigsb\pixelpong\frame\FrameBuffer;
use stigsb\pixelpong\server\Event;
use stigsb\pixelpong\bitmap\Sprite;

class JoystickTestGameLoop extends BaseGameLoop
{

    /** @var \stigsb\pixelpong\bitmap\Sprite */
    private $p1UpSprite;

    /** @var \stigsb\pixelpong\bitmap\Sprite */
    private $p1DownSprite;

    /** @var Sprite */
    private $p2UpSprite;

    /** @var \stigsb\pixelpong\bitmap\Sprite */
    private $p2DownSprite;

    public function __construct(FrameBuffer $frameBuffer, ContainerInterface $container)
    {
        parent::__construct($frameBuffer, $container);
        $this->p1UpSprite     = $this->bitmapLoader->loadSprite('joy_up',    6,  6);
        $this->p1DownSprite   = $this->bitmapLoader->loadSprite('joy_down',  6, 17);
        $this->p2UpSprite     = $this->bitmapLoader->loadSprite('joy_up',   35,  6);
        $this->p2DownSprite   = $this->bitmapLoader->loadSprite('joy_down', 35, 17);
        $this->addSprite($this->p1UpSprite);
        $this->addSprite($this->p1DownSprite);
        $this->addSprite($this->p2UpSprite);
        $this->addSprite($this->p2DownSprite);
    }

    /**
     * This method is called when the game enters this game loop.
     * This is where you would replace the default frame among other things.
     */
    public function onEnter()
    {
        parent::onEnter();
        $this->p1UpSprite->setVisible(false);
        $this->p1UpSprite->setVisible(false);
        $this->p1UpSprite->setVisible(false);
        $this->p1UpSprite->setVisible(false);
    }

    /**
     * An input event occurs (joystick action).
     * @param Event $event
     */
    public function onEvent(Event $event)
    {
        if ($event->device == Event::DEVICE_JOY_1 && $event->eventType == Event::JOY_AXIS_Y) {
            switch ($event->value) {
                case Event::AXIS_UP:
                    $this->p1UpSprite->setVisible(true);
                    break;
                case Event::AXIS_DOWN:
                    $this->p1DownSprite->setVisible(true);
                    break;
                default:
                    $this->p1UpSprite->setVisible(false);
                    $this->p1DownSprite->setVisible(false);
                    break;
            }
        } elseif ($event->device == Event::DEVICE_JOY_2 && $event->eventType == Event::JOY_AXIS_Y) {
            switch ($event->value) {
                case Event::AXIS_UP:
                    $this->p2UpSprite->setVisible(true);
                    break;
                case Event::AXIS_DOWN:
                    $this->p2DownSprite->setVisible(true);
                    break;
                default:
                    $this->p2UpSprite->setVisible(false);
                    $this->p2DownSprite->setVisible(false);
                    break;
            }
        }
    }

}
