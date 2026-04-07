use crate::bitmap::{Bitmap, SimpleBitmap};
use crate::bitmap::loader::BitmapLoader;
use crate::frame::FrameBuffer;
use crate::gameloop::main_game::MainGameLoop;
use crate::gameloop::{GameLoop, GameLoopTransition};
use crate::server::event::{self, Event};

pub struct PressStartToPlayGameLoop {
    press_start_frame: Option<Vec<i8>>,
    to_play_frame: Option<Vec<i8>>,
    enter_time: f64,
    previous_time: u64,
    initialized: bool,
}

impl PressStartToPlayGameLoop {
    pub fn new() -> Self {
        Self {
            press_start_frame: None,
            to_play_frame: None,
            enter_time: 0.0,
            previous_time: 0,
            initialized: false,
        }
    }
}

fn now_secs() -> f64 {
    std::time::SystemTime::now()
        .duration_since(std::time::UNIX_EPOCH)
        .unwrap()
        .as_secs_f64()
}

impl GameLoop for PressStartToPlayGameLoop {
    fn on_enter(&mut self, fb: &mut dyn FrameBuffer, loader: &mut BitmapLoader) {
        if !self.initialized {
            self.press_start_frame = Some(loader.load_bitmap("press_start").unwrap().pixels().to_vec());
            self.to_play_frame = Some(loader.load_bitmap("to_play").unwrap().pixels().to_vec());
            self.initialized = true;
        }
        fb.set_background_frame(self.press_start_frame.as_ref().unwrap().clone());
        self.previous_time = 0;
        self.enter_time = now_secs();
    }

    fn on_frame_update(&mut self, fb: &mut dyn FrameBuffer) {
        let elapsed = (now_secs() - self.enter_time) as u64;
        if elapsed > self.previous_time {
            match elapsed % 4 {
                0 => {
                    fb.set_background_frame(self.press_start_frame.as_ref().unwrap().clone());
                }
                2 => {
                    fb.set_background_frame(self.to_play_frame.as_ref().unwrap().clone());
                }
                _ => {}
            }
        }
        self.previous_time = elapsed;
    }

    fn on_event(&mut self, event: &Event) -> Option<GameLoopTransition> {
        if event.event_type == event::JOY_BUTTON_1 && event.value == event::BUTTON_NEUTRAL {
            Some(GameLoopTransition::SwitchTo(Box::new(MainGameLoop::new())))
        } else {
            None
        }
    }
}
