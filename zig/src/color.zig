/// C64-inspired 16-color palette.
pub const Color = enum(i8) {
    transparent = -1,
    black = 0,
    white = 1,
    red = 2,
    cyan = 3,
    purple = 4,
    green = 5,
    blue = 6,
    yellow = 7,
    orange = 8,
    brown = 9,
    light_red = 10,
    dark_grey = 11,
    grey = 12,
    light_green = 13,
    light_blue = 14,
    light_grey = 15,

    pub fn isTransparent(self: Color) bool {
        return self == .transparent;
    }

    pub fn toIndex(self: Color) ?u4 {
        const v = @intFromEnum(self);
        if (v < 0) return null;
        return @intCast(v);
    }

    /// Hex color strings for the palette (indices 0-15).
    pub const palette = [16][]const u8{
        "#000000", // black
        "#fcf9fc", // white
        "#933a4c", // red
        "#b6fafa", // cyan
        "#d27ded", // purple
        "#6acf6f", // green
        "#4f44d8", // blue
        "#fbfb8b", // yellow
        "#d89c5b", // orange
        "#7f5307", // brown
        "#ef839f", // light_red
        "#575753", // dark_grey
        "#a3a7a7", // grey
        "#b7fbbf", // light_green
        "#a397ff", // light_blue
        "#d0d0d0", // light_grey
    };
};
