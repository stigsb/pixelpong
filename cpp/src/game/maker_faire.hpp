#pragma once

#include <chrono>
#include <vector>

#include "game/game_loop.hpp"

namespace pixelpong {

class MakerFaire : public GameLoop {
public:
    MakerFaire(GameContext& ctx, SwitchLoopFn switch_fn)
        : GameLoop(ctx, std::move(switch_fn)) {
        for (const auto& name : {"trondheim", "maker", "faire"}) {
            auto bmp = ctx_.bitmap_loader.load_bitmap(name);
            if (bmp) {
                frames_.push_back((*bmp)->pixels());
            }
        }
    }

    void on_enter() override {
        current_frame_ = 0;
        last_switch_ = std::chrono::steady_clock::now();
        if (!frames_.empty()) {
            ctx_.frame_buffer.set_background(frames_[0]);
        }
    }

    void on_frame_update() override {
        if (frames_.empty()) return;

        auto now = std::chrono::steady_clock::now();
        auto elapsed = std::chrono::duration_cast<std::chrono::seconds>(now - last_switch_).count();
        if (elapsed >= 1) {
            current_frame_ = (current_frame_ + 1) % static_cast<int>(frames_.size());
            ctx_.frame_buffer.set_background(frames_[current_frame_]);
            last_switch_ = now;
        }
        GameLoop::on_frame_update();
    }

    void on_event(const Event&) override {}

private:
    std::vector<std::vector<Color>> frames_;
    int current_frame_ = 0;
    std::chrono::steady_clock::time_point last_switch_;
};

}  // namespace pixelpong
