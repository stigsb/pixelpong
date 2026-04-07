#pragma once

#include <chrono>

#include "game/game_loop.hpp"

namespace pixelpong {

class MainGame;

class PressStart : public GameLoop {
public:
    PressStart(GameContext& ctx, SwitchLoopFn switch_fn)
        : GameLoop(ctx, std::move(switch_fn)) {
        auto ps = ctx_.bitmap_loader.load_bitmap("press_start");
        if (ps) {
            press_start_pixels_ = (*ps)->pixels();
        }
        auto tp = ctx_.bitmap_loader.load_bitmap("to_play");
        if (tp) {
            to_play_pixels_ = (*tp)->pixels();
        }
    }

    void on_enter() override {
        enter_time_ = std::chrono::steady_clock::now();
        previous_time_ = enter_time_;
        if (!press_start_pixels_.empty()) {
            ctx_.frame_buffer.set_background(press_start_pixels_);
        }
    }

    void on_frame_update() override {
        auto now = std::chrono::steady_clock::now();
        auto elapsed = std::chrono::duration_cast<std::chrono::seconds>(now - enter_time_).count();
        bool show_press_start = (elapsed % 4) < 2;

        if (show_press_start && !press_start_pixels_.empty()) {
            ctx_.frame_buffer.set_background(press_start_pixels_);
        } else if (!show_press_start && !to_play_pixels_.empty()) {
            ctx_.frame_buffer.set_background(to_play_pixels_);
        }
        GameLoop::on_frame_update();
    }

    void on_event(const Event& event) override;

private:
    std::vector<Color> press_start_pixels_;
    std::vector<Color> to_play_pixels_;
    std::chrono::steady_clock::time_point enter_time_;
    std::chrono::steady_clock::time_point previous_time_;
};

}  // namespace pixelpong
