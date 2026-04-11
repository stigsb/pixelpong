<?php

namespace stigsb\pixelpong\bitmap;

class Sprite
{
    /** @var Bitmap */
    private $bitmap;

    /** @var int */
    private $x;

    /** @var int */
    private $y;

    /** @var bool */
    private $visible;

    /**
     * @param Bitmap $bitmap
     * @param int $xpos
     * @param int $ypos
     */
    public function __construct(Bitmap $bitmap, $xpos=0, $ypos=0, $visible=true)
    {
        $this->bitmap = $bitmap;
        $this->x = $xpos;
        $this->y = $ypos;
        $this->visible = $visible;
    }

    /**
     * @param int $x
     * @param int $y
     */
    public function moveTo($x, $y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    /**
     * @param boolean $visible
     * @return $this
     */
    public function setVisible($visible)
    {
        $this->visible = $visible;
        return $this;
    }

    /**
     * @return int
     */
    public function getX()
    {
        return $this->x;
    }

    /**
     * @return int
     */
    public function getY()
    {
        return $this->y;
    }

    /**
     * @return boolean
     */
    public function isVisible()
    {
        return $this->visible;
    }

    /**
     * @return Bitmap
     */
    public function getBitmap()
    {
        return $this->bitmap;
    }

}
