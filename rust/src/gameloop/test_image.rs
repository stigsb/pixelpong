use crate::bitmap::loader::BitmapLoader;
use crate::frame::FrameBuffer;
use crate::gameloop::base::BaseGameLoop;
use crate::gameloop::press_start::PressStartToPlayGameLoop;
use crate::gameloop::{GameLoop, GameLoopTransition};
use crate::server::event::{self, Event};

pub struct TestImageScreen {
    base: BaseGameLoop,
    initialized: bool,
}

impl TestImageScreen {
    pub fn new() -> Self {
        Self {
            base: BaseGameLoop::new(),
            initialized: false,
        }
    }
}

impl GameLoop for TestImageScreen {
    fn on_enter(&mut self, fb: &mut dyn FrameBuffer, loader: &mut BitmapLoader) {
        if !self.initialized {
            self.base.background = Some(loader.load_bitmap("test_image").unwrap().clone());
            self.initialized = true;
        }
        self.base.on_enter(fb);
    }

    fn on_frame_update(&mut self, fb: &mut dyn FrameBuffer) {
        self.base.on_frame_update(fb);
    }

    fn on_event(&mut self, event: &Event) -> Option<GameLoopTransition> {
        if event.event_type == event::JOY_BUTTON_1 && event.value == event::BUTTON_NEUTRAL {
            Some(GameLoopTransition::SwitchTo(Box::new(PressStartToPlayGameLoop::new())))
        } else {
            None
        }
    }
}
