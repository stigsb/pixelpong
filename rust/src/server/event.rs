// Devices
pub const DEVICE_JOY_1: i32 = 1;
pub const DEVICE_JOY_2: i32 = 2;
pub const DEVICE_KEYBOARD: i32 = 3;

// Event types
pub const JOY_AXIS_X: i32 = 1;
pub const JOY_AXIS_Y: i32 = 2;
pub const JOY_BUTTON_1: i32 = 3;

// Event values
pub const BUTTON_DOWN: i32 = 1;
pub const BUTTON_NEUTRAL: i32 = 0;
pub const AXIS_UP: i32 = -1;
pub const AXIS_DOWN: i32 = 1;
pub const AXIS_LEFT: i32 = -1;
pub const AXIS_RIGHT: i32 = 1;
pub const AXIS_NEUTRAL: i32 = 0;

#[derive(Debug, Clone)]
pub struct Event {
    pub device: i32,
    pub event_type: i32,
    pub value: i32,
}

impl Event {
    pub fn new(device: i32, event_type: i32, value: i32) -> Self {
        Self { device, event_type, value }
    }
}
