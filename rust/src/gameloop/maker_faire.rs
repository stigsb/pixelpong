use crate::bitmap::Bitmap;
use crate::bitmap::loader::BitmapLoader;
use crate::frame::FrameBuffer;
use crate::gameloop::{GameLoop, GameLoopTransition};
use crate::server::event::Event;

pub struct TrondheimMakerFaireScreen {
    frames: Vec<Vec<i8>>,
    current_frame_index: usize,
    previous_time: u64,
    initialized: bool,
}

impl TrondheimMakerFaireScreen {
    pub fn new() -> Self {
        Self {
            frames: Vec::new(),
            current_frame_index: 0,
            previous_time: 0,
            initialized: false,
        }
    }
}

fn now_secs() -> u64 {
    std::time::SystemTime::now()
        .duration_since(std::time::UNIX_EPOCH)
        .unwrap()
        .as_secs()
}

impl GameLoop for TrondheimMakerFaireScreen {
    fn on_enter(&mut self, fb: &mut dyn FrameBuffer, loader: &mut BitmapLoader) {
        if !self.initialized {
            for name in &["trondheim", "maker", "faire"] {
                let bmp = loader.load_bitmap(name).unwrap();
                self.frames.push(bmp.pixels().to_vec());
            }
            self.initialized = true;
        }
        self.current_frame_index = 0;
        fb.set_background_frame(self.frames[0].clone());
        self.previous_time = now_secs();
    }

    fn on_frame_update(&mut self, fb: &mut dyn FrameBuffer) {
        let now = now_secs();
        if now > self.previous_time {
            self.current_frame_index = (self.current_frame_index + 1) % self.frames.len();
            fb.set_background_frame(self.frames[self.current_frame_index].clone());
        }
        self.previous_time = now;
    }

    fn on_event(&mut self, _event: &Event) -> Option<GameLoopTransition> {
        None
    }
}
