#pragma once

#include "frame/frame_encoder.hpp"

namespace pixelpong {

struct PlayerConnection {
    JsonEncoder encoder;
    bool input_enabled = true;
    bool output_enabled = true;

    explicit PlayerConnection(int width, int height)
        : encoder(width, height) {}
};

}  // namespace pixelpong
