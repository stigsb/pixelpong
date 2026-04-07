#pragma once

#include <cstdint>

namespace pixelpong {

struct Event {
    enum class Device : uint8_t {
        Joy1 = 1,
        Joy2 = 2,
        Keyboard = 3,
    };

    enum class Type : uint8_t {
        AxisX = 1,
        AxisY = 2,
        Button1 = 3,
    };

    static constexpr int8_t ButtonDown = 1;
    static constexpr int8_t ButtonNeutral = 0;

    static constexpr int8_t AxisUp = -1;
    static constexpr int8_t AxisDown = 1;
    static constexpr int8_t AxisLeft = -1;
    static constexpr int8_t AxisRight = 1;
    static constexpr int8_t AxisNeutral = 0;

    Device device;
    Type type;
    int8_t value;
};

}  // namespace pixelpong
