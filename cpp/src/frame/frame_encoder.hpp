#pragma once

#include <optional>
#include <span>
#include <string>
#include <vector>

#include <nlohmann/json.hpp>

#include "frame/color.hpp"
#include "frame/frame_buffer.hpp"

namespace pixelpong {

class JsonEncoder {
public:
    JsonEncoder(int width, int height)
        : width_(width), height_(height),
          previous_(width * height, Color::Black) {}

    std::optional<std::string> encode(std::span<const Color> frame) {
        // Find changed pixels
        nlohmann::json diff;
        int changed = 0;
        int total = static_cast<int>(frame.size());

        for (int i = 0; i < total; ++i) {
            if (frame[i] != previous_[i]) {
                diff[std::to_string(i)] = static_cast<int>(frame[i]);
                ++changed;
            }
        }

        // Copy current frame for next comparison
        previous_.assign(frame.begin(), frame.end());

        if (changed == 0) {
            return std::nullopt;
        }

        if (changed < total / 3) {
            // Delta
            return nlohmann::json{{"frameDelta", diff}}.dump();
        }

        // Full frame — omit black pixels
        nlohmann::json pixels;
        for (int i = 0; i < total; ++i) {
            if (frame[i] != Color::Black) {
                pixels[std::to_string(i)] = static_cast<int>(frame[i]);
            }
        }
        return nlohmann::json{{"frame", pixels}}.dump();
    }

    static std::string encode_frame_info(const FrameBuffer& fb) {
        nlohmann::json pal;
        for (int i = 0; i < color_count; ++i) {
            pal[std::to_string(i)] = std::string(palette[i]);
        }
        return nlohmann::json{
            {"frameInfo", {
                {"width", fb.width()},
                {"height", fb.height()},
                {"palette", pal},
            }}
        }.dump();
    }

private:
    int width_;
    int height_;
    std::vector<Color> previous_;
};

class AsciiEncoder {
public:
    AsciiEncoder(int width, int height) : width_(width), height_(height) {}

    std::string encode(std::span<const Color> frame) {
        std::string result;
        result.reserve((width_ + 1) * height_);
        for (int y = 0; y < height_; ++y) {
            for (int x = 0; x < width_; ++x) {
                Color c = frame[y * width_ + x];
                result += color_to_char(c);
            }
            result += '\n';
        }
        return result;
    }

private:
    static char color_to_char(Color c) {
        switch (c) {
            case Color::Black: case Color::Transparent: return '.';
            case Color::White: return '#';
            default: {
                int v = static_cast<int>(c);
                if (v >= 0 && v <= 9) return '0' + v;
                if (v >= 10 && v <= 15) return 'a' + (v - 10);
                return '?';
            }
        }
    }

    int width_;
    int height_;
};

}  // namespace pixelpong
