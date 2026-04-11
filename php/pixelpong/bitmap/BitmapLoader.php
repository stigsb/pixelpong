<?php

namespace stigsb\pixelpong\bitmap;

use stigsb\pixelpong\server\Color;

class BitmapLoader
{
    public static $colorMap = [
        ' ' => Color::TRANSPARENT,
        '.' => Color::BLACK,
        '0' => Color::BLACK,
        '#' => Color::WHITE,
        '1' => Color::WHITE,
        '2' => Color::RED,
        '3' => Color::CYAN,
        '4' => Color::PURPLE,
        '5' => Color::GREEN,
        '6' => Color::BLUE,
        '7' => Color::YELLOW,
        '8' => Color::ORANGE,
        '9' => Color::BROWN,
        'a' => Color::LIGHT_RED,
        'b' => Color::DARK_GREY,
        'c' => Color::GREY,
        'd' => Color::LIGHT_GREEN,
        'e' => Color::LIGHT_BLUE,
        'f' => Color::LIGHT_GREY,
    ];

/*
    const BLACK         = 0;
    const WHITE         = 1;
    const RED           = 2;
    const CYAN          = 3;
    const PURPLE        = 4;
    const GREEN         = 5;
    const BLUE          = 6;
    const YELLOW        = 7;
    const ORANGE        = 8;
    const BROWN         = 9;
    const LIGHT_RED     = 10;
    const DARK_GREY     = 11;
    const GREY          = 12;
    const LIGHT_GREEN   = 13;
    const LIGHT_BLUE    = 14;
    const LIGHT_GREY    = 15;
*/
    /** @var array[string]Bitmap */
    private $bitmapCache;

    /** @var string[]  list of directories where bitmap files may be found */
    private $bitmapPath;

    /**
     * @param string $bitmapPath  colon-separated list of directories in which bitmaps are found
     */
    public function __construct($bitmapPath)
    {
        $this->bitmapPath = explode(':', $bitmapPath);
        $this->bitmapCache = [];
    }

    /**
     * @param string $name   sprite bitmap name
     * @param int $x
     * @param int $y
     * @return Sprite
     */
    public function loadSprite($name, $x=0, $y=0)
    {
        $bitmap = self::loadBitmap($name);
        return new Sprite($bitmap, $x, $y);
    }

    /**
     * @param string $name  bitmap name
     * @return Bitmap
     */
    public function loadBitmap($name) {
        if (!isset($this->bitmapCache[$name])) {
            $file = $this->findBitmapFileInPath($name);
            if ($file) {
                $this->bitmapCache[$name] = $this->loadBitmapFromFile($file);
            }
        }
        if (!isset($this->bitmapCache[$name])) {
            throw new \RuntimeException("bitmap not found: {$name}");
        }
        return $this->bitmapCache[$name];
    }

    /**
     * @param string $file  bitmap file (.txt extension)
     * @return Bitmap
     */
    protected function loadBitmapFromFile($file) {
        $lines = [];
        $width = 0;
        foreach (file($file) as $line) {
            $line = rtrim($line, "|\r\n");
            if (strlen($line) > $width) {
                $width = strlen($line);
            }
            $lines[] = $line;
        }
        $height = count($lines);
        $pixels = array_fill(0, $width * $height, Color::TRANSPARENT);
        foreach ($lines as $y => $line) {
            $max_x = min($width, strlen($line));
            for ($x = 0; $x < $max_x; ++$x) {
                if (!isset(self::$colorMap[$line[$x]])) {
                    continue;
                }
                $pixels[($y * $width) + $x] = self::$colorMap[$line[$x]];
            }
        }
        return new SimpleBitmap($width, $height, $pixels);
    }

    /**
     * @param string $bitmapName
     * @return string|null
     */
    protected function findBitmapFileInPath($bitmapName)
    {
        foreach ($this->bitmapPath as $dir) {
            $txt_file = "{$dir}/{$bitmapName}.txt";
            if (file_exists($txt_file)) {
                return $txt_file;
            }
        }
        return null;
    }
//    /**
//     * @param string $file  bitmap file (.gif extension)
//     * @return Sprite
//     */
//    private function loadSpriteFromGifFile($file)
//    {
//        $image = imagecreatefromgif($file);
//        $width = imagesx($image);
//        $height = imagesy($image);
//    }
}
