#pragma once

#include "bitmap/bitmap.hpp"

namespace pixelpong {

class Sprite {
public:
    Sprite() = default;

    explicit Sprite(const Bitmap* bitmap, int x = 0, int y = 0, bool visible = true)
        : bitmap_(bitmap), x_(x), y_(y), visible_(visible) {}

    void move_to(int x, int y) { x_ = x; y_ = y; }
    void set_visible(bool v) { visible_ = v; }

    int x() const { return x_; }
    int y() const { return y_; }
    bool visible() const { return visible_; }
    const Bitmap* bitmap() const { return bitmap_; }

private:
    const Bitmap* bitmap_ = nullptr;
    int x_ = 0;
    int y_ = 0;
    bool visible_ = true;
};

}  // namespace pixelpong
