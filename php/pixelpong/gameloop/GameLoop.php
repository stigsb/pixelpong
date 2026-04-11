<?php

namespace stigsb\pixelpong\gameloop;

use stigsb\pixelpong\server\Event;

interface GameLoop
{
    /**
     * This method is called when the game enters this game loop.
     * This is where you would replace the default frame among other things.
     */
    public function onEnter();

    public function onFrameUpdate();

    /**
     * An input event occurs (joystick action).
     * @param Event $event
     */
    public function onEvent(Event $event);

}
