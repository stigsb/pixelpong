<?php

namespace stigsb\pixelpong\gameloop;

use Psr\Container\ContainerInterface;
use stigsb\pixelpong\bitmap\BitmapLoader;
use stigsb\pixelpong\frame\FrameBuffer;
use stigsb\pixelpong\bitmap\Sprite;

abstract class BaseGameLoop implements GameLoop
{
    /** @var \stigsb\pixelpong\frame\FrameBuffer */
    protected $frameBuffer;

    /** @var \stigsb\pixelpong\bitmap\BitmapLoader */
    protected $bitmapLoader;

    /** @var \stigsb\pixelpong\bitmap\Bitmap */
    protected $background;

    /** @var \stigsb\pixelpong\bitmap\Sprite[] */
    protected $sprites;

    /** @var ContainerInterface */
    protected $container;

    public function __construct(FrameBuffer $frameBuffer, ContainerInterface $container)
    {
        $this->frameBuffer = $frameBuffer;
        $this->container = $container;
        $this->bitmapLoader = $container->get(BitmapLoader::class);
        $this->sprites = [];
    }

    /**
     * This method is called when the game enters this game loop.
     * This is where you would replace the default frame among other things.
     */
    public function onEnter()
    {
        if ($this->background) {
            $this->frameBuffer->setBackgroundFrame($this->background->getPixels());
        }
    }

    public function onFrameUpdate()
    {
        $this->renderVisibleSprites();
    }

    public function addSprite(Sprite $sprite)
    {
        $this->sprites[] = $sprite;
    }

    public function renderVisibleSprites()
    {
        foreach ($this->sprites as $sprite) {
            if ($sprite->isVisible()) {
                $this->frameBuffer->drawBitmapAt($sprite->getBitmap(), $sprite->getX(), $sprite->getY());
            }
        }
    }
}