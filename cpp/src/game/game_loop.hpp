#pragma once

#include <functional>
#include <memory>
#include <vector>

#include "bitmap/bitmap.hpp"
#include "bitmap/bitmap_loader.hpp"
#include "bitmap/font_loader.hpp"
#include "bitmap/sprite.hpp"
#include "frame/frame_buffer.hpp"
#include "game/event.hpp"

namespace pixelpong {

struct GameContext {
    FrameBuffer& frame_buffer;
    BitmapLoader& bitmap_loader;
    FontLoader& font_loader;
};

using SwitchLoopFn = std::function<void(std::unique_ptr<class GameLoop>)>;

class GameLoop {
public:
    virtual ~GameLoop() = default;

    virtual void on_enter() {
        if (!background_.empty()) {
            ctx_.frame_buffer.set_background(background_.pixels());
        }
    }

    virtual void on_frame_update() {
        render_visible_sprites();
    }

    virtual void on_event(const Event& event) = 0;

protected:
    GameLoop(GameContext& ctx, SwitchLoopFn switch_fn)
        : ctx_(ctx), switch_loop_(std::move(switch_fn)) {}

    void add_sprite(Sprite* sprite) {
        sprites_.push_back(sprite);
    }

    void render_visible_sprites() {
        for (auto* sprite : sprites_) {
            if (sprite->visible()) {
                ctx_.frame_buffer.draw_bitmap(*sprite->bitmap(), sprite->x(), sprite->y());
            }
        }
    }

    GameContext& ctx_;
    SwitchLoopFn switch_loop_;
    Bitmap background_;
    std::vector<Sprite*> sprites_;
};

}  // namespace pixelpong
