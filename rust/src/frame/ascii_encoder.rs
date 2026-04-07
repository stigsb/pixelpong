use crate::frame::FrameEncoder;
use crate::bitmap::loader::inverse_color_map;
use crate::server::color;

pub struct AsciiFrameEncoder {
    width: usize,
    height: usize,
    color_char_map: std::collections::HashMap<i8, u8>,
}

impl AsciiFrameEncoder {
    pub fn new(width: usize, height: usize) -> Self {
        Self {
            width,
            height,
            color_char_map: inverse_color_map(),
        }
    }
}

impl FrameEncoder for AsciiFrameEncoder {
    fn encode_frame(&mut self, frame: &[i8]) -> Option<String> {
        let mut output = String::with_capacity(self.height * (self.width + 1));
        for y in 0..self.height {
            if y > 0 {
                output.push('\n');
            }
            for x in 0..self.width {
                let color = frame[y * self.width + x];
                if color == color::TRANSPARENT {
                    output.push('.');
                    continue;
                }
                let ch = self.color_char_map.get(&color).copied().unwrap_or(b' ');
                output.push(ch as char);
            }
        }
        Some(output)
    }

    fn encode_frame_info(&self, _width: usize, _height: usize) -> String {
        String::new()
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn test_ascii_encode_simple() {
        let mut enc = AsciiFrameEncoder::new(3, 2);
        let frame = vec![
            color::WHITE, color::BLACK, color::WHITE,
            color::BLACK, color::WHITE, color::BLACK,
        ];
        let encoded = enc.encode_frame(&frame).unwrap();
        assert_eq!(encoded, "#.#\n.#.");
    }

    #[test]
    fn test_ascii_frame_info_empty() {
        let enc = AsciiFrameEncoder::new(3, 2);
        assert_eq!(enc.encode_frame_info(3, 2), "");
    }
}
