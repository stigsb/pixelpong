<?php


namespace stigsb\pixelpong\server;


class Event
{
    /* Devices */
    const DEVICE_JOY_1      = 1;
    const DEVICE_JOY_2      = 2;
    const DEVICE_KEYBOARD   = 3;

    /* Event Types */
    const JOY_AXIS_X        = 1;
    const JOY_AXIS_Y        = 2;
    const JOY_BUTTON_1      = 3;

    /* Event Values */
    const BUTTON_DOWN       = 1;
    const BUTTON_NEUTRAL    = 0;

    const AXIS_UP           = -1;
    const AXIS_DOWN         = 1;
    const AXIS_LEFT         = -1;
    const AXIS_RIGHT        = 1;
    const AXIS_NEUTRAL      = 0;

    /** @var int */
    public $device;

    /** @var int */
    public $eventType;

    /** @var int */
    public $value;

    /**
     * @param string $device
     */
    public function __construct($device, $type, $value)
    {
        $this->device = $device;
        $this->eventType = $type;
        $this->value = $value;
    }

}
