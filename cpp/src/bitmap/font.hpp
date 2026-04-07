#pragma once

#include <unordered_map>
#include <vector>

#include "bitmap/bitmap.hpp"
#include "frame/color.hpp"

namespace pixelpong {

class Font {
public:
    static constexpr int BG = 0;
    static constexpr int FG = 1;

    Font() = default;

    Font(std::unordered_map<char, std::vector<int>> character_bitmaps,
         int width, int height, char blank_char)
        : character_bitmaps_(std::move(character_bitmaps)),
          width_(width), height_(height), blank_char_(blank_char) {}

    int width() const { return width_; }
    int height() const { return height_; }

    const std::vector<int>& pixels_for_character(char ch) const {
        auto it = character_bitmaps_.find(ch);
        if (it != character_bitmaps_.end()) return it->second;
        it = character_bitmaps_.find(blank_char_);
        if (it != character_bitmaps_.end()) return it->second;
        static const std::vector<int> empty;
        return empty;
    }

private:
    std::unordered_map<char, std::vector<int>> character_bitmaps_;
    int width_ = 0;
    int height_ = 0;
    char blank_char_ = ' ';
};

inline Bitmap make_text_bitmap(const Font& font, std::string_view text,
                               Color color, int spacing = 1) {
    int cw = font.width();
    int ch = font.height();
    int num_chars = static_cast<int>(text.size());
    if (num_chars == 0) return Bitmap(0, 0);
    int full_width = (cw * num_chars) + ((num_chars - 1) * spacing);
    int full_height = ch;
    std::vector<Color> pixels(full_width * full_height, Color::Black);
    for (int i = 0; i < num_chars; ++i) {
        int cox = (cw + spacing) * i;
        const auto& char_pixels = font.pixels_for_character(text[i]);
        if (char_pixels.empty()) continue;
        for (int y = 0; y < ch; ++y) {
            for (int x = 0; x < cw; ++x) {
                if (char_pixels[y * cw + x] == Font::FG) {
                    pixels[full_width * y + cox + x] = color;
                }
            }
        }
    }
    return Bitmap(full_width, full_height, std::move(pixels));
}

}  // namespace pixelpong
