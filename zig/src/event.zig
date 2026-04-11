pub const Device = enum(u8) {
    joy_1 = 1,
    joy_2 = 2,
    keyboard = 3,
};

pub const EventType = enum(u8) {
    joy_axis_x = 1,
    joy_axis_y = 2,
    joy_button_1 = 3,
};

pub const AxisValue = enum(i8) {
    up = -1,
    neutral = 0,
    down = 1,

    // Aliases for X axis
    pub const left: AxisValue = .up;
    pub const right: AxisValue = .down;
};

pub const ButtonValue = enum(u8) {
    neutral = 0,
    down = 1,
};

pub const Event = struct {
    device: Device,
    event_type: EventType,
    /// Raw value: for axes -1/0/1, for buttons 0/1
    value: i8,

    pub fn axisValue(self: Event) AxisValue {
        return @enumFromInt(self.value);
    }

    pub fn buttonValue(self: Event) ButtonValue {
        return @enumFromInt(@as(u8, @intCast(self.value)));
    }
};
