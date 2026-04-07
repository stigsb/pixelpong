use crate::frame::FrameEncoder;
use crate::server::color;

pub struct JsonFrameEncoder {
    width: usize,
    height: usize,
    previous_frame: Vec<i8>,
}

impl JsonFrameEncoder {
    pub fn new(width: usize, height: usize) -> Self {
        let size = width * height;
        Self {
            width,
            height,
            previous_frame: vec![color::BLACK; size],
        }
    }
}

impl FrameEncoder for JsonFrameEncoder {
    fn encode_frame(&mut self, frame: &[i8]) -> Option<String> {
        let mut diff: Vec<(usize, i8)> = Vec::new();
        for (i, (&cur, &prev)) in frame.iter().zip(self.previous_frame.iter()).enumerate() {
            if cur != prev {
                diff.push((i, cur));
            }
        }
        self.previous_frame = frame.to_vec();

        if diff.is_empty() {
            return None;
        }

        if diff.len() < frame.len() / 3 {
            let mut map = serde_json::Map::new();
            for (idx, color) in &diff {
                map.insert(idx.to_string(), serde_json::Value::Number((*color as i64).into()));
            }
            let obj = serde_json::json!({ "frameDelta": map });
            Some(obj.to_string())
        } else {
            let mut map = serde_json::Map::new();
            for (idx, &c) in frame.iter().enumerate() {
                if c != color::BLACK {
                    map.insert(idx.to_string(), serde_json::Value::Number((c as i64).into()));
                }
            }
            let obj = serde_json::json!({ "frame": map });
            Some(obj.to_string())
        }
    }

    fn encode_frame_info(&self, width: usize, height: usize) -> String {
        let palette: serde_json::Map<String, serde_json::Value> = color::get_palette()
            .into_iter()
            .map(|(id, hex)| (id.to_string(), serde_json::Value::String(hex.to_string())))
            .collect();

        serde_json::json!({
            "frameInfo": {
                "width": width,
                "height": height,
                "palette": palette,
            }
        }).to_string()
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn test_encode_sparse_frame() {
        let mut enc = JsonFrameEncoder::new(3, 2);
        let frame = vec![1, 0, 0, 0, 1, 0];
        let encoded = enc.encode_frame(&frame).unwrap();
        let v: serde_json::Value = serde_json::from_str(&encoded).unwrap();
        assert!(v.get("frame").is_some() || v.get("frameDelta").is_some());
    }

    #[test]
    fn test_no_change_returns_none() {
        let mut enc = JsonFrameEncoder::new(2, 2);
        let frame = vec![0, 0, 0, 0];
        assert!(enc.encode_frame(&frame).is_none());
    }

    #[test]
    fn test_delta_encoding() {
        let mut enc = JsonFrameEncoder::new(3, 3);
        let frame1 = vec![1, 1, 1, 1, 1, 1, 1, 1, 1];
        let _ = enc.encode_frame(&frame1);
        let mut frame2 = frame1.clone();
        frame2[4] = 2;
        let encoded = enc.encode_frame(&frame2).unwrap();
        let v: serde_json::Value = serde_json::from_str(&encoded).unwrap();
        assert!(v.get("frameDelta").is_some());
        assert_eq!(v["frameDelta"]["4"], 2);
    }

    #[test]
    fn test_frame_info() {
        let enc = JsonFrameEncoder::new(47, 27);
        let info = enc.encode_frame_info(47, 27);
        let v: serde_json::Value = serde_json::from_str(&info).unwrap();
        assert_eq!(v["frameInfo"]["width"], 47);
        assert_eq!(v["frameInfo"]["height"], 27);
        assert!(v["frameInfo"]["palette"]["0"].is_string());
    }
}
