<?php

namespace stigsb\pixelpong\bitmap;

class TextBitmap extends SimpleBitmap
{
    /**
     * @param Font $font
     * @param string $text
     * @param int $color
     * @param int $spacing
     * @throws FontException
     */
    public function __construct(Font $font, $text, $color, $spacing=1)
    {
        $cw = $font->getWidth();
        $ch = $font->getHeight();
        // no support for UTF-8 or newlines!
        $num_chars = strlen($text);
        $full_width = ($cw * $num_chars) + (($num_chars - 1) * $spacing);
        $full_height = $ch;
        $pixels = array_fill(0, $full_width * $full_height, 0);
        for ($i = 0; $i < $num_chars; ++$i) {
            $char = ord($text[$i]);
            $cox = ($cw + $spacing) * $i;
            $char_pixels = $font->getPixelsForCharacter($char);
            for ($y = 0; $y < $ch; ++$y) {
                for ($x = 0; $x < $cw; ++$x) {
                    if ($char_pixels[($y * $cw) + $x]) {
                        $pixels[($full_width * $y) + $cox + $x] = $color;
                    }
                }
            }
        }
        parent::__construct($full_width, $full_height, $pixels);
    }
}
