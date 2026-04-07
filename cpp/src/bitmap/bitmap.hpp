#pragma once

#include <algorithm>
#include <cassert>
#include <cstdint>
#include <vector>

#include "frame/color.hpp"

namespace pixelpong {

class Bitmap {
public:
    Bitmap() = default;

    Bitmap(int width, int height, std::vector<Color> pixels)
        : width_(width), height_(height), pixels_(std::move(pixels)) {
        assert(std::ssize(pixels_) == width_ * height_);
    }

    Bitmap(int width, int height, Color fill = Color::Transparent)
        : width_(width), height_(height), pixels_(width * height, fill) {}

    int width() const { return width_; }
    int height() const { return height_; }

    Color pixel(int x, int y) const {
        assert(x >= 0 && x < width_ && y >= 0 && y < height_);
        return pixels_[y * width_ + x];
    }

    void set_pixel(int x, int y, Color c) {
        assert(x >= 0 && x < width_ && y >= 0 && y < height_);
        pixels_[y * width_ + x] = c;
    }

    const std::vector<Color>& pixels() const { return pixels_; }

    bool empty() const { return pixels_.empty(); }

private:
    int width_ = 0;
    int height_ = 0;
    std::vector<Color> pixels_;
};

class ScrollingBitmap {
public:
    ScrollingBitmap(const Bitmap& bitmap, int view_width, int view_height,
                    int x_offset = 0, int y_offset = 0)
        : bitmap_(bitmap), view_width_(view_width), view_height_(view_height),
          x_offset_(x_offset), y_offset_(y_offset) {}

    int width() const { return view_width_; }
    int height() const { return view_height_; }

    void scroll_to(int x, int y) {
        x_offset_ = x;
        y_offset_ = y;
    }

    std::vector<Color> get_pixels() const {
        std::vector<Color> result(view_width_ * view_height_, Color::Transparent);
        for (int y = 0; y < view_height_; ++y) {
            int src_y = y + y_offset_;
            if (src_y < 0 || src_y >= bitmap_.height()) continue;
            for (int x = 0; x < view_width_; ++x) {
                int src_x = x + x_offset_;
                if (src_x < 0 || src_x >= bitmap_.width()) continue;
                result[y * view_width_ + x] = bitmap_.pixel(src_x, src_y);
            }
        }
        return result;
    }

    const Bitmap& original_bitmap() const { return bitmap_; }

private:
    const Bitmap& bitmap_;
    int view_width_;
    int view_height_;
    int x_offset_;
    int y_offset_;
};

}  // namespace pixelpong
