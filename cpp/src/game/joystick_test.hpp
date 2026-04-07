#pragma once

#include "game/game_loop.hpp"

namespace pixelpong {

class JoystickTest : public GameLoop {
public:
    JoystickTest(GameContext& ctx, SwitchLoopFn switch_fn)
        : GameLoop(ctx, std::move(switch_fn)) {
        auto load = [&](const std::string& name) -> Sprite {
            auto s = ctx_.bitmap_loader.load_sprite(name);
            return s ? *s : Sprite();
        };
        p1_up_ = load("joy_up");
        p1_down_ = load("joy_down");
        p2_up_ = load("joy_up");
        p2_down_ = load("joy_down");

        p2_up_.move_to(ctx_.frame_buffer.width() / 2, p2_up_.y());
        p2_down_.move_to(ctx_.frame_buffer.width() / 2, p2_down_.y());

        add_sprite(&p1_up_);
        add_sprite(&p1_down_);
        add_sprite(&p2_up_);
        add_sprite(&p2_down_);
    }

    void on_enter() override {
        p1_up_.set_visible(false);
        p1_down_.set_visible(false);
        p2_up_.set_visible(false);
        p2_down_.set_visible(false);
        GameLoop::on_enter();
    }

    void on_event(const Event& event) override {
        if (event.type != Event::Type::AxisY) return;

        Sprite* up = nullptr;
        Sprite* down = nullptr;
        if (event.device == Event::Device::Joy1) {
            up = &p1_up_;
            down = &p1_down_;
        } else if (event.device == Event::Device::Joy2) {
            up = &p2_up_;
            down = &p2_down_;
        } else {
            return;
        }

        up->set_visible(event.value == Event::AxisUp);
        down->set_visible(event.value == Event::AxisDown);
    }

private:
    Sprite p1_up_, p1_down_;
    Sprite p2_up_, p2_down_;
};

}  // namespace pixelpong
