<?php

namespace stigsb\pixelpong\bitmap;

class FontLoader
{
    const SKIP_CHAR = 32;
    const NEWLINE_CHAR = 10;

    private $fontDir;

    public function __construct($fontDir)
    {
        $this->fontDir = $fontDir;
    }

    public function loadFont($name)
    {
        $png_file = "{$this->fontDir}/{$name}.png";
        $image = imagecreatefrompng($png_file);
//        $sx = imagesx($image);
//        $sy = imagesy($image);
        $font_meta = $this->getFontMetaData($name);
        $oy = 0;
        $char_pixels = $font_meta->width * $font_meta->height;
        $character_bitmaps = [];
        $pixel_color = $font_meta->pixelColor;
        if (preg_match('/^#([0-9a-f]{6})$/', $pixel_color, $m)) {
            $pixel_color = hexdec($m[1]);
        }
        foreach ($font_meta->characterLines as $charLine) {
            for ($i = 0, $ox = 0; $i < strlen($charLine); ++$i, $ox += $font_meta->width + $font_meta->charSpacing[0]) {
                if ($charLine[$i] == $font_meta->blankChar) {
                    continue;
                }
                $pixels = array_fill(0, $char_pixels, Font::BG);
                for ($y = 0; $y < $font_meta->height; ++$y) {
                    for ($x = 0; $x < $font_meta->width; ++$x) {
                        if (imagecolorat($image, $ox + $x, $oy + $y) == $pixel_color) {
                            $pixels[($y * $font_meta->width) + $x] = Font::FG;
                        }
                    }
                }
                $character_bitmaps[ord($charLine[$i])] = $pixels;
            }
            $oy += $font_meta->height + $font_meta->charSpacing[1];
        }
        return $this->createFontWithMetaDataAndBitmaps($font_meta, $character_bitmaps);
    }

    protected function getFontMetaData($name)
    {
        $charmap_file = "{$this->fontDir}/{$name}.json";
        return json_decode(file_get_contents($charmap_file));
    }

    protected function createFontWithMetaDataAndBitmaps($font_meta, $character_bitmaps)
    {
        return new Font($character_bitmaps, $font_meta->width, $font_meta->height, ord($font_meta->blankChar));
    }

}