#pragma once

#include <array>
#include <cstdint>
#include <string_view>

namespace pixelpong {

enum class Color : int8_t {
    Transparent = -1,
    Black = 0,
    White = 1,
    Red = 2,
    Cyan = 3,
    Purple = 4,
    Green = 5,
    Blue = 6,
    Yellow = 7,
    Orange = 8,
    Brown = 9,
    LightRed = 10,
    DarkGrey = 11,
    Grey = 12,
    LightGreen = 13,
    LightBlue = 14,
    LightGrey = 15,
};

inline constexpr int color_count = 16;

inline constexpr std::array<std::string_view, color_count> palette = {{
    "#000000",  // Black
    "#fcf9fc",  // White
    "#933a4c",  // Red
    "#b6fafa",  // Cyan
    "#d27ded",  // Purple
    "#6acf6f",  // Green
    "#4f44d8",  // Blue
    "#fbfb8b",  // Yellow
    "#d89c5b",  // Orange
    "#7f5307",  // Brown
    "#ef839f",  // LightRed
    "#575753",  // DarkGrey
    "#a3a7a7",  // Grey
    "#b7fbbf",  // LightGreen
    "#a397ff",  // LightBlue
    "#d0d0d0",  // LightGrey
}};

inline constexpr std::string_view color_hex(Color c) {
    auto idx = static_cast<int>(c);
    if (idx < 0 || idx >= color_count) return "#000000";
    return palette[idx];
}

}  // namespace pixelpong
