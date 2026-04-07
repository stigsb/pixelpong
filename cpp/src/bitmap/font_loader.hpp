#pragma once

#include <expected>
#include <filesystem>
#include <fstream>
#include <string>

#include <nlohmann/json.hpp>
#include "stb_image.h"

#include "bitmap/font.hpp"

namespace pixelpong {

class FontLoader {
public:
    explicit FontLoader(std::filesystem::path font_dir)
        : font_dir_(std::move(font_dir)) {}

    std::expected<Font, std::string> load_font(const std::string& name) {
        auto json_path = font_dir_ / (name + ".json");
        auto png_path = font_dir_ / (name + ".png");

        // Load JSON metadata
        std::ifstream json_file(json_path);
        if (!json_file) {
            return std::unexpected("cannot open font metadata: " + json_path.string());
        }
        nlohmann::json meta;
        json_file >> meta;

        int font_w = meta["width"];
        int font_h = meta["height"];
        char blank_char = meta["blankChar"].get<std::string>()[0];
        auto char_spacing = meta["charSpacing"];
        int x_spacing = char_spacing[0];
        int y_spacing = char_spacing[1];
        std::string pixel_color_str = meta["pixelColor"];

        // Parse pixel color from hex
        uint32_t pixel_color = 0;
        if (pixel_color_str.size() == 7 && pixel_color_str[0] == '#') {
            pixel_color = std::stoul(pixel_color_str.substr(1), nullptr, 16);
        }

        // Load PNG
        int img_w, img_h, channels;
        auto* img_data = stbi_load(png_path.string().c_str(), &img_w, &img_h, &channels, 3);
        if (!img_data) {
            return std::unexpected("cannot load font PNG: " + png_path.string());
        }

        auto get_pixel_rgb = [&](int x, int y) -> uint32_t {
            int idx = (y * img_w + x) * 3;
            return (static_cast<uint32_t>(img_data[idx]) << 16) |
                   (static_cast<uint32_t>(img_data[idx + 1]) << 8) |
                   static_cast<uint32_t>(img_data[idx + 2]);
        };

        std::unordered_map<char, std::vector<int>> character_bitmaps;
        int oy = 0;
        int char_pixels = font_w * font_h;

        for (const auto& char_line : meta["characterLines"]) {
            std::string line = char_line;
            int ox = 0;
            for (char ch : line) {
                if (ch == blank_char) {
                    ox += font_w + x_spacing;
                    continue;
                }
                std::vector<int> pixels(char_pixels, Font::BG);
                for (int y = 0; y < font_h; ++y) {
                    for (int x = 0; x < font_w; ++x) {
                        if (get_pixel_rgb(ox + x, oy + y) == pixel_color) {
                            pixels[y * font_w + x] = Font::FG;
                        }
                    }
                }
                character_bitmaps[ch] = std::move(pixels);
                ox += font_w + x_spacing;
            }
            oy += font_h + y_spacing;
        }

        stbi_image_free(img_data);

        return Font(std::move(character_bitmaps), font_w, font_h, blank_char);
    }

private:
    std::filesystem::path font_dir_;
};

}  // namespace pixelpong
