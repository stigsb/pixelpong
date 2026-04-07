#pragma once

#include <expected>
#include <filesystem>
#include <fstream>
#include <optional>
#include <string>
#include <unordered_map>
#include <vector>

#include "bitmap/bitmap.hpp"
#include "bitmap/sprite.hpp"
#include "frame/color.hpp"

namespace pixelpong {

class BitmapLoader {
public:
    explicit BitmapLoader(std::vector<std::filesystem::path> search_paths)
        : search_paths_(std::move(search_paths)) {}

    std::expected<const Bitmap*, std::string> load_bitmap(const std::string& name) {
        if (auto it = cache_.find(name); it != cache_.end()) {
            return &it->second;
        }
        auto path = find_file(name);
        if (!path) {
            return std::unexpected("bitmap not found: " + name);
        }
        auto result = load_from_file(*path);
        if (!result) {
            return std::unexpected(result.error());
        }
        auto [it, _] = cache_.emplace(name, std::move(*result));
        return &it->second;
    }

    std::expected<Sprite, std::string> load_sprite(const std::string& name, int x = 0, int y = 0) {
        auto bmp = load_bitmap(name);
        if (!bmp) return std::unexpected(bmp.error());
        return Sprite(*bmp, x, y);
    }

private:
    static constexpr Color char_to_color(char ch) {
        switch (ch) {
            case ' ': return Color::Transparent;
            case '.': case '0': return Color::Black;
            case '#': case '1': return Color::White;
            case '2': return Color::Red;
            case '3': return Color::Cyan;
            case '4': return Color::Purple;
            case '5': return Color::Green;
            case '6': return Color::Blue;
            case '7': return Color::Yellow;
            case '8': return Color::Orange;
            case '9': return Color::Brown;
            case 'a': return Color::LightRed;
            case 'b': return Color::DarkGrey;
            case 'c': return Color::Grey;
            case 'd': return Color::LightGreen;
            case 'e': return Color::LightBlue;
            case 'f': return Color::LightGrey;
            default: return Color::Transparent;
        }
    }

    std::expected<Bitmap, std::string> load_from_file(const std::filesystem::path& path) {
        std::ifstream file(path);
        if (!file) {
            return std::unexpected("cannot open file: " + path.string());
        }

        std::vector<std::string> lines;
        int width = 0;
        std::string line;
        while (std::getline(file, line)) {
            // Strip trailing pipe and whitespace
            if (!line.empty() && line.back() == '|') {
                line.pop_back();
            }
            while (!line.empty() && (line.back() == '\r' || line.back() == '\n')) {
                line.pop_back();
            }
            if (std::ssize(line) > width) {
                width = static_cast<int>(line.size());
            }
            lines.push_back(std::move(line));
        }

        int height = static_cast<int>(lines.size());
        std::vector<Color> pixels(width * height, Color::Transparent);
        for (int y = 0; y < height; ++y) {
            int max_x = std::min(width, static_cast<int>(lines[y].size()));
            for (int x = 0; x < max_x; ++x) {
                pixels[y * width + x] = char_to_color(lines[y][x]);
            }
        }
        return Bitmap(width, height, std::move(pixels));
    }

    std::optional<std::filesystem::path> find_file(const std::string& name) {
        for (const auto& dir : search_paths_) {
            auto path = dir / (name + ".txt");
            if (std::filesystem::exists(path)) {
                return path;
            }
        }
        return std::nullopt;
    }

    std::vector<std::filesystem::path> search_paths_;
    std::unordered_map<std::string, Bitmap> cache_;
};

}  // namespace pixelpong
