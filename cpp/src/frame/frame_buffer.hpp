#pragma once

#include <cassert>
#include <span>
#include <vector>

#include "bitmap/bitmap.hpp"
#include "frame/color.hpp"

namespace pixelpong {

class FrameBuffer {
public:
    FrameBuffer(int width, int height)
        : width_(width), height_(height),
          background_(width * height, Color::Black),
          current_(width * height, Color::Black) {}

    int width() const { return width_; }
    int height() const { return height_; }

    Color pixel(int x, int y) const {
        assert(x >= 0 && x < width_ && y >= 0 && y < height_);
        return current_[y * width_ + x];
    }

    void set_pixel(int x, int y, Color color) {
        assert(x >= 0 && x < width_ && y >= 0 && y < height_);
        current_[y * width_ + x] = color;
    }

    void set_background(const std::vector<Color>& frame) {
        assert(std::ssize(frame) == width_ * height_);
        background_ = frame;
    }

    void draw_bitmap(const Bitmap& bitmap, int xoff, int yoff) {
        render_queue_.push_back({&bitmap, xoff, yoff});
    }

    std::span<const Color> swap() {
        render_queued_bitmaps();
        front_ = current_;
        current_ = background_;
        render_queue_.clear();
        return front_;
    }

    std::span<const Color> current_frame() const { return current_; }

private:
    struct QueuedBitmap {
        const Bitmap* bitmap;
        int x;
        int y;
    };

    void render_queued_bitmaps() {
        for (const auto& [bitmap, xoff, yoff] : render_queue_) {
            int w = bitmap->width();
            int h = bitmap->height();
            const auto& pixels = bitmap->pixels();
            for (int y = 0; y < h; ++y) {
                int yy = yoff + y;
                if (yy < 0 || yy >= height_) continue;
                for (int x = 0; x < w; ++x) {
                    int xx = xoff + x;
                    if (xx < 0 || xx >= width_) continue;
                    Color pixel = pixels[y * w + x];
                    if (pixel != Color::Transparent) {
                        current_[yy * width_ + xx] = pixel;
                    }
                }
            }
        }
    }

    int width_;
    int height_;
    std::vector<Color> background_;
    std::vector<Color> current_;
    std::vector<Color> front_;
    std::vector<QueuedBitmap> render_queue_;
};

}  // namespace pixelpong
