pub const TRANSPARENT: i8 = -1;
pub const BLACK: i8 = 0;
pub const WHITE: i8 = 1;
pub const RED: i8 = 2;
pub const CYAN: i8 = 3;
pub const PURPLE: i8 = 4;
pub const GREEN: i8 = 5;
pub const BLUE: i8 = 6;
pub const YELLOW: i8 = 7;
pub const ORANGE: i8 = 8;
pub const BROWN: i8 = 9;
pub const LIGHT_RED: i8 = 10;
pub const DARK_GREY: i8 = 11;
pub const GREY: i8 = 12;
pub const LIGHT_GREEN: i8 = 13;
pub const LIGHT_BLUE: i8 = 14;
pub const LIGHT_GREY: i8 = 15;

pub fn get_palette() -> Vec<(i8, &'static str)> {
    vec![
        (BLACK, "#000000"),
        (WHITE, "#fcf9fc"),
        (RED, "#933a4c"),
        (CYAN, "#b6fafa"),
        (PURPLE, "#d27ded"),
        (GREEN, "#6acf6f"),
        (BLUE, "#4f44d8"),
        (YELLOW, "#fbfb8b"),
        (ORANGE, "#d89c5b"),
        (BROWN, "#7f5307"),
        (LIGHT_RED, "#ef839f"),
        (DARK_GREY, "#575753"),
        (GREY, "#a3a7a7"),
        (LIGHT_GREEN, "#b7fbbf"),
        (LIGHT_BLUE, "#a397ff"),
        (LIGHT_GREY, "#d0d0d0"),
    ]
}
