<?php


namespace stigsb\pixelpong\server;


class Color
{
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

    const TRANSPARENT   = -1;

    protected static $palette = [
// From http://www.gamebase64.com/forum/viewtopic.php?t=1304&sid=e3569083afbb1c4ad92c3c961a52fb88
        self::BLACK         => '#000000',
        self::WHITE         => '#fcf9fc',
        self::RED           => '#933a4c',
        self::CYAN          => '#b6fafa',
        self::PURPLE        => '#d27ded',
        self::GREEN         => '#6acf6f',
        self::BLUE          => '#4f44d8',
        self::YELLOW        => '#fbfb8b',
        self::ORANGE        => '#d89c5b',
        self::BROWN         => '#7f5307',
        self::LIGHT_RED     => '#ef839f',
        self::DARK_GREY     => '#575753',
        self::GREY          => '#a3a7a7',
        self::LIGHT_GREEN   => '#b7fbbf',
        self::LIGHT_BLUE    => '#a397ff',
        self::LIGHT_GREY    => '#d0d0d0',

// From http://unusedino.de/ec64/technical/misc/vic656x/colors/
//        self::BLACK         => '#000000',
//        self::WHITE         => '#ffffff',
//        self::RED           => '#68372B',
//        self::CYAN          => '#70A4B2',
//        self::PURPLE        => '#6F3D86',
//        self::GREEN         => '#588D43',
//        self::BLUE          => '#352879',
//        self::YELLOW        => '#B8C76F',
//        self::ORANGE        => '#6F4F25',
//        self::BROWN         => '#433900',
//        self::LIGHT_RED     => '#9A6759',
//        self::DARK_GREY     => '#444444',
//        self::GREY          => '#6C6C6C',
//        self::LIGHT_GREEN   => '#9AD284',
//        self::LIGHT_BLUE    => '#6C5EB5',
//        self::LIGHT_GREY    => '#959595',
    ];

    public static function getPalette()
    {
        return self::$palette;
    }
}
