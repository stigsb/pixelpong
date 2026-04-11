<?php

namespace stigsb\pixelpong\bitmap;

/**
 * A fixed-width monochrome font.
 */
class Font
{
    const BG = 0;
    const FG = 1;

    /** @var array[string]array */
    private $characterBitmaps;

    /** @var int  font character width */
    private $width;

    /** @var int  font character height*/
    private $height;

    /** @var int  which character in the font (should be blank) to use for unknown characters */
    private $blankChar;

    /**
     * @param array $characterBitmaps
     * @param int $width
     * @param int $height
     * @param int $blankChar
     */
    public function __construct(array $characterBitmaps, $width, $height, $blankChar)
    {
        $this->characterBitmaps = $characterBitmaps;
        $this->width = $width;
        $this->height = $height;
        $this->blankChar = $blankChar;
    }

    /**
     * @param int $char  character code
     * @return array
     */
    public function getPixelsForCharacter($char)
    {
        if (!isset($this->characterBitmaps[$char])) {
            $char = $this->blankChar;
        }
        return $this->characterBitmaps[$char];
    }

    /**
     * @return int
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getHeight()
    {
        return $this->height;
    }

}
