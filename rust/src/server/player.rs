use crate::frame::FrameEncoder;
use crate::frame::json_encoder::JsonFrameEncoder;

pub struct PlayerConnection {
    pub encoder: JsonFrameEncoder,
    pub input_enabled: bool,
    pub output_enabled: bool,
}

impl PlayerConnection {
    pub fn new(width: usize, height: usize) -> Self {
        Self {
            encoder: JsonFrameEncoder::new(width, height),
            input_enabled: false,
            output_enabled: false,
        }
    }
}
