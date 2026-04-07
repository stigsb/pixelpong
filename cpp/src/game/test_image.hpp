#pragma once

#include "game/game_loop.hpp"
#include "game/press_start.hpp"

namespace pixelpong {

class TestImage : public GameLoop {
public:
    TestImage(GameContext& ctx, SwitchLoopFn switch_fn)
        : GameLoop(ctx, std::move(switch_fn)) {
        auto bmp = ctx_.bitmap_loader.load_bitmap("test_image");
        if (bmp) background_ = **bmp;
    }

    void on_event(const Event& event) override {
        if (event.type == Event::Type::Button1 && event.value == Event::ButtonNeutral) {
            switch_loop_(std::make_unique<PressStart>(ctx_, switch_loop_));
        }
    }
};

}  // namespace pixelpong
