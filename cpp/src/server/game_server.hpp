#pragma once

#include <cstdio>
#include <cstdlib>
#include <memory>
#include <string>
#include <unordered_map>

#include <App.h>
#include <nlohmann/json.hpp>

#include "frame/frame_buffer.hpp"
#include "frame/frame_encoder.hpp"
#include "game/event.hpp"
#include "game/game_loop.hpp"
#include "game/main_game.hpp"
#include "game/test_image.hpp"
#include "server/player_connection.hpp"

extern "C" {
#include "libusockets.h"
}

namespace pixelpong {

struct PerSocketData {
    int id = 0;
};

class GameServer {
public:
    GameServer(int port, const std::string& bind_addr, double fps,
               GameContext& ctx)
        : port_(port), bind_addr_(bind_addr), fps_(fps), ctx_(ctx) {}

    void run() {
        auto switch_fn = [this](std::unique_ptr<GameLoop> loop) {
            game_loop_ = std::move(loop);
            game_loop_->on_enter();
        };

        game_loop_ = std::make_unique<TestImage>(ctx_, switch_fn);
        game_loop_->on_enter();

        uWS::App().ws<PerSocketData>("/*", {
            .open = [this](auto* ws) {
                int id = next_id_++;
                ws->getUserData()->id = id;
                connections_.emplace(id, Connection{ws, PlayerConnection(
                    ctx_.frame_buffer.width(), ctx_.frame_buffer.height())});

                // Send frame info to new connection
                auto info = JsonEncoder::encode_frame_info(ctx_.frame_buffer);
                ws->send(info, uWS::OpCode::TEXT);
                std::printf("Client connected (id=%d)\n", id);
            },
            .message = [this](auto* ws, std::string_view message, uWS::OpCode) {
                on_message(ws, message);
            },
            .close = [this](auto* ws, int, std::string_view) {
                int id = ws->getUserData()->id;
                connections_.erase(id);
                std::printf("Disconnected (id=%d)\n", id);
            },
        }).listen(bind_addr_, port_, [this](auto* listen_socket) {
            if (listen_socket) {
                std::printf("Listening on %s:%d at %.1f FPS\n",
                            bind_addr_.c_str(), port_, fps_);

                // Set up frame update timer using uSockets
                struct us_loop_t* loop = (struct us_loop_t*)uWS::Loop::get();
                struct us_timer_t* timer = us_create_timer(loop, 0, sizeof(GameServer*));
                *(GameServer**)us_timer_ext(timer) = this;
                int interval_ms = static_cast<int>(1000.0 / fps_);
                us_timer_set(timer, [](struct us_timer_t* t) {
                    auto* self = *(GameServer**)us_timer_ext(t);
                    self->on_frame_update();
                }, interval_ms, interval_ms);
            } else {
                std::fprintf(stderr, "Failed to listen on %s:%d\n",
                             bind_addr_.c_str(), port_);
                std::exit(1);
            }
        }).run();
    }

private:
    struct Connection {
        uWS::WebSocket<false, true, PerSocketData>* ws;
        PlayerConnection player;
    };

    void on_frame_update() {
        if (!game_loop_) return;
        game_loop_->on_frame_update();
        auto frame = ctx_.frame_buffer.swap();
        for (auto& [id, conn] : connections_) {
            if (!conn.player.output_enabled) continue;
            auto encoded = conn.player.encoder.encode(frame);
            if (encoded) {
                conn.ws->send(*encoded, uWS::OpCode::TEXT);
            }
        }
    }

    void on_message(uWS::WebSocket<false, true, PerSocketData>* ws,
                    std::string_view raw) {
        try {
            auto msg = nlohmann::json::parse(raw);

            // Handle compact event format
            if (msg.contains("V") && msg.contains("D") && msg.contains("T")) {
                Event event{
                    static_cast<Event::Device>(msg["D"].get<int>()),
                    static_cast<Event::Type>(msg["T"].get<int>()),
                    msg["V"].get<int8_t>(),
                };
                if (game_loop_) game_loop_->on_event(event);
                return;
            }

            int id = ws->getUserData()->id;
            auto it = connections_.find(id);
            if (it == connections_.end()) return;
            auto& conn = it->second;

            if (msg.contains("input")) {
                conn.player.input_enabled = msg["input"].get<bool>();
            }
            if (msg.contains("output")) {
                conn.player.output_enabled = msg["output"].get<bool>();
            }
            if (msg.contains("event")) {
                auto& ev = msg["event"];
                Event event{
                    static_cast<Event::Device>(ev["device"].get<int>()),
                    static_cast<Event::Type>(ev["eventType"].get<int>()),
                    ev["value"].get<int8_t>(),
                };
                if (game_loop_) game_loop_->on_event(event);
            }
            if (msg.contains("command")) {
                auto cmd = msg["command"].get<std::string>();
                if (cmd == "restart") {
                    std::printf("Restarting on request from client!\n");
                    std::exit(0);
                }
            }
        } catch (const nlohmann::json::exception& e) {
            std::printf("JSON parse error: %s\n", e.what());
        }
    }

    int port_;
    std::string bind_addr_;
    double fps_;
    GameContext& ctx_;
    std::unique_ptr<GameLoop> game_loop_;
    std::unordered_map<int, Connection> connections_;
    int next_id_ = 0;
};

}  // namespace pixelpong
