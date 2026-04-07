#pragma once

#include <chrono>
#include <cmath>
#include <cstdio>
#include <variant>

#include "game/game_loop.hpp"
#include "game/press_start.hpp"

namespace pixelpong {

struct Initializing {
    std::optional<double> timestamp;
};
struct Waiting {};
struct Playing {
    double start_timestamp = 0.0;
    double last_speedup_timestamp = 0.0;
};
struct GameOver {
    int winning_side = 0;
};

using GameState = std::variant<Initializing, Waiting, Playing, GameOver>;

class MainGame : public GameLoop {
public:
    static constexpr double BALL_SPEED = 3.0;
    static constexpr double PADDLE_SPEED = 10.0;
    static constexpr double PADDLE_INFLUENCE = 0.5;
    static constexpr int BALL_SPEEDUP_EVERY_N_SECS = 10;
    static constexpr double BALL_SPEEDUP_FACTOR = 1.10;
    static constexpr double FRAME_EDGE_SIZE = 1.0;

    static constexpr int LEFT = 0;
    static constexpr int RIGHT = 1;
    static constexpr int TOP = 0;
    static constexpr int BOTTOM = 1;

    MainGame(GameContext& ctx, SwitchLoopFn switch_fn)
        : GameLoop(ctx, std::move(switch_fn)) {
        initialize_game();
    }

    void on_enter() override {
        GameLoop::on_enter();
        reset_game();
    }

    void on_event(const Event& event) override {
        std::visit([&](auto& state) {
            using T = std::decay_t<decltype(state)>;
            if constexpr (std::is_same_v<T, Waiting>) {
                if (event.type == Event::Type::Button1 &&
                    event.value == Event::ButtonNeutral) {
                    start_game();
                }
            } else if constexpr (std::is_same_v<T, Playing>) {
                if (event.type == Event::Type::AxisY) {
                    int paddle = device_to_paddle(event.device);
                    if (paddle < 0) return;
                    if (event.value == Event::AxisNeutral) {
                        update_paddle_position(paddle);
                    }
                    current_y_axis_[paddle] = event.value;
                }
            } else if constexpr (std::is_same_v<T, GameOver>) {
                if (event.type == Event::Type::Button1 &&
                    event.value == Event::ButtonNeutral) {
                    reset_game();
                }
            }
        }, state_);
    }

    void on_frame_update() override {
        frame_timestamp_ = now_seconds();

        std::visit([&](auto& state) {
            using T = std::decay_t<decltype(state)>;
            if constexpr (std::is_same_v<T, Initializing>) {
                if (!state.timestamp) {
                    state.timestamp = frame_timestamp_;
                } else {
                    approx_frame_time_ = frame_timestamp_ - *state.timestamp;
                    std::printf("approxFrameTime: %f\n", approx_frame_time_);
                    state_ = Waiting{};
                }
            } else if constexpr (std::is_same_v<T, Playing>) {
                for (int paddle : {LEFT, RIGHT}) {
                    update_paddle_position(paddle);
                }
                update_paddle_sprite_positions();
                update_ball_position(state);
            }
        }, state_);

        GameLoop::on_frame_update();
    }

private:
    void initialize_game() {
        auto bmp = ctx_.bitmap_loader.load_bitmap("main_game");
        if (bmp) background_ = **bmp;

        display_width_ = ctx_.frame_buffer.width();
        display_height_ = ctx_.frame_buffer.height();

        auto left_paddle = ctx_.bitmap_loader.load_sprite("paddle");
        auto right_paddle = ctx_.bitmap_loader.load_sprite("paddle");
        if (left_paddle) paddles_[LEFT] = *left_paddle;
        if (right_paddle) paddles_[RIGHT] = *right_paddle;
        add_sprite(&paddles_[LEFT]);
        add_sprite(&paddles_[RIGHT]);

        paddle_height_ = paddles_[LEFT].bitmap()->height();
        paddle_width_ = paddles_[LEFT].bitmap()->width();
        paddle_min_y_ = FRAME_EDGE_SIZE;
        paddle_max_y_ = display_height_ - paddle_height_ - FRAME_EDGE_SIZE;

        auto ball_sprite = ctx_.bitmap_loader.load_sprite("ball");
        if (ball_sprite) ball_ = *ball_sprite;
        add_sprite(&ball_);

        ball_height_ = ball_.bitmap()->height();
        ball_width_ = ball_.bitmap()->width();

        paddle_pos_x_[LEFT] = 1.0;
        paddle_pos_x_[RIGHT] = display_width_ - 1.0 - paddle_width_;

        ball_paddle_limit_x_[LEFT] = paddle_pos_x_[LEFT] + paddle_width_;
        ball_paddle_limit_x_[RIGHT] = paddle_pos_x_[RIGHT] - paddle_width_;

        ball_edge_limit_y_[TOP] = 1.0;
        ball_edge_limit_y_[BOTTOM] = static_cast<double>(display_height_ - ball_height_);

        frame_timestamp_ = now_seconds();
    }

    void reset_game() {
        last_y_axis_update_time_[LEFT] = 0.0;
        last_y_axis_update_time_[RIGHT] = 0.0;
        current_y_axis_[LEFT] = Event::AxisNeutral;
        current_y_axis_[RIGHT] = Event::AxisNeutral;

        double paddle_middle_y = (display_height_ / 2.0) - (paddle_height_ / 2.0);
        paddle_positions_[LEFT] = paddle_middle_y;
        paddle_positions_[RIGHT] = paddle_middle_y;

        ball_pos_[0] = ball_paddle_limit_x_[LEFT];
        ball_pos_[1] = 12.0;
        ball_delta_[0] = 0.0;
        ball_delta_[1] = 0.0;

        state_ = Initializing{};
        approx_frame_time_ = 0.0;

        update_ball_sprite_position();
        update_paddle_sprite_positions();
    }

    void start_game() {
        ball_delta_[0] = PADDLE_SPEED * approx_frame_time_;
        ball_delta_[1] = PADDLE_SPEED * approx_frame_time_;
        double now = now_seconds();
        state_ = Playing{now, now};
        std::printf("Starting game!\n");
    }

    void update_ball_position(Playing& state) {
        ball_pos_[0] += ball_delta_[0];
        ball_pos_[1] += ball_delta_[1];

        if (ball_pos_[1] <= ball_edge_limit_y_[TOP]) {
            bounce_ball_on_edge(TOP);
        } else if (ball_pos_[1] >= ball_edge_limit_y_[BOTTOM]) {
            bounce_ball_on_edge(BOTTOM);
        }

        if (ball_pos_[0] <= ball_paddle_limit_x_[LEFT]) {
            if (ball_hit_paddle(LEFT)) {
                bounce_ball_on_paddle(LEFT, state);
                std::printf("bounce ball on left paddle\n");
            } else {
                player_won(RIGHT);
            }
        } else if (ball_pos_[0] >= ball_paddle_limit_x_[RIGHT]) {
            std::printf("past right paddle!\n");
            if (ball_hit_paddle(RIGHT)) {
                bounce_ball_on_paddle(RIGHT, state);
                std::printf("bounce ball on right paddle\n");
            } else {
                player_won(LEFT);
            }
        }

        std::printf("new ball position: [%d,%d]\n",
                     static_cast<int>(ball_pos_[0]),
                     static_cast<int>(ball_pos_[1]));
        update_ball_sprite_position();
    }

    void update_ball_sprite_position() {
        ball_.move_to(static_cast<int>(ball_pos_[0]),
                      static_cast<int>(ball_pos_[1]));
    }

    void update_paddle_sprite_positions() {
        for (int p : {LEFT, RIGHT}) {
            paddles_[p].move_to(static_cast<int>(paddle_pos_x_[p]),
                                static_cast<int>(paddle_positions_[p]));
        }
    }

    void update_paddle_position(int paddle) {
        double now = frame_timestamp_;
        double elapsed = now - last_y_axis_update_time_[paddle];
        double new_pos = paddle_positions_[paddle] +
                         (PADDLE_SPEED * elapsed * current_y_axis_[paddle]);
        new_pos = std::clamp(new_pos, paddle_min_y_, paddle_max_y_);
        paddle_positions_[paddle] = new_pos;
        last_y_axis_update_time_[paddle] = now;
    }

    void player_won(int side) {
        std::printf("%s side won!\n", side == LEFT ? "Left" : "Right");
        state_ = GameOver{side};
    }

    bool ball_hit_paddle(int paddle) const {
        double ball_y = ball_pos_[1];
        double paddle_y_min = paddle_positions_[paddle] - ball_height_;
        double paddle_y_max = paddle_positions_[paddle] + paddle_height_ + ball_height_;
        return (ball_y > paddle_y_min && ball_y < paddle_y_max);
    }

    void bounce_ball_on_paddle(int paddle, Playing& state) {
        double bounce_back = ball_paddle_limit_x_[paddle] - ball_pos_[0];
        ball_pos_[0] = ball_paddle_limit_x_[paddle] + bounce_back;
        ball_delta_[0] *= -1.0;

        int paddle_direction = current_y_axis_[paddle];
        if (paddle_direction != Event::AxisNeutral) {
            double influence = std::abs(ball_delta_[0]) * PADDLE_INFLUENCE;
            ball_delta_[1] += influence * paddle_direction;

            double max_y = std::abs(ball_delta_[0]) * 1.5;
            double min_y = std::abs(ball_delta_[0]) * 0.35;
            double sign = ball_delta_[1] >= 0 ? 1.0 : -1.0;
            double abs_y = std::abs(ball_delta_[1]);
            ball_delta_[1] = sign * std::clamp(abs_y, min_y, max_y);
        }

        maybe_speed_up_ball(state);
    }

    void bounce_ball_on_edge(int edge) {
        double bounce_back = ball_edge_limit_y_[edge] - ball_pos_[1];
        ball_pos_[1] = ball_edge_limit_y_[edge] + bounce_back;
        ball_delta_[1] *= -1.0;
    }

    void maybe_speed_up_ball(Playing& state) {
        double time_since_last = frame_timestamp_ - state.last_speedup_timestamp;
        std::printf("timeSinceLast=%f\n", time_since_last);
        if (time_since_last >= BALL_SPEEDUP_EVERY_N_SECS) {
            ball_delta_[0] *= BALL_SPEEDUP_FACTOR;
            ball_delta_[1] *= BALL_SPEEDUP_FACTOR;
            state.last_speedup_timestamp = frame_timestamp_;
            std::printf("Speeding up ball!\n");
        }
    }

    static int device_to_paddle(Event::Device dev) {
        switch (dev) {
            case Event::Device::Joy1: return LEFT;
            case Event::Device::Joy2: return RIGHT;
            default: return -1;
        }
    }

    static double now_seconds() {
        auto now = std::chrono::steady_clock::now();
        return std::chrono::duration<double>(now.time_since_epoch()).count();
    }

    int display_width_ = 0;
    int display_height_ = 0;

    Sprite paddles_[2];
    Sprite ball_;

    double paddle_positions_[2] = {};
    double last_y_axis_update_time_[2] = {};
    int current_y_axis_[2] = {};
    double paddle_pos_x_[2] = {};
    double ball_delta_[2] = {};
    double ball_pos_[2] = {};
    double ball_paddle_limit_x_[2] = {};
    double ball_edge_limit_y_[2] = {};

    int paddle_height_ = 0;
    int paddle_width_ = 0;
    int ball_height_ = 0;
    int ball_width_ = 0;
    double paddle_min_y_ = 0.0;
    double paddle_max_y_ = 0.0;

    double frame_timestamp_ = 0.0;
    double approx_frame_time_ = 0.0;

    GameState state_ = Initializing{};
};

// Define PressStart::on_event here to break circular dependency
inline void PressStart::on_event(const Event& event) {
    if (event.type == Event::Type::Button1 && event.value == Event::ButtonNeutral) {
        switch_loop_(std::make_unique<MainGame>(ctx_, switch_loop_));
    }
}

}  // namespace pixelpong
