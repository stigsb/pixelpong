use crate::bitmap::loader::BitmapLoader;
use crate::frame::FrameBuffer;
use crate::gameloop::base::BaseGameLoop;
use crate::gameloop::{GameLoop, GameLoopTransition};
use crate::server::event::{self, Event};

pub struct JoystickTestGameLoop {
    base: BaseGameLoop,
    p1_up: usize,
    p1_down: usize,
    p2_up: usize,
    p2_down: usize,
    initialized: bool,
}

impl JoystickTestGameLoop {
    pub fn new() -> Self {
        Self {
            base: BaseGameLoop::new(),
            p1_up: 0,
            p1_down: 0,
            p2_up: 0,
            p2_down: 0,
            initialized: false,
        }
    }
}

impl GameLoop for JoystickTestGameLoop {
    fn on_enter(&mut self, fb: &mut dyn FrameBuffer, loader: &mut BitmapLoader) {
        if !self.initialized {
            self.p1_up = self.base.add_sprite(loader.load_sprite("joy_up", 6, 6).unwrap());
            self.p1_down = self.base.add_sprite(loader.load_sprite("joy_down", 6, 17).unwrap());
            self.p2_up = self.base.add_sprite(loader.load_sprite("joy_up", 35, 6).unwrap());
            self.p2_down = self.base.add_sprite(loader.load_sprite("joy_down", 35, 17).unwrap());
            self.initialized = true;
        }
        self.base.on_enter(fb);
        // Bug fix: PHP version sets p1_up 4 times, we set all 4 correctly
        self.base.sprites[self.p1_up].set_visible(false);
        self.base.sprites[self.p1_down].set_visible(false);
        self.base.sprites[self.p2_up].set_visible(false);
        self.base.sprites[self.p2_down].set_visible(false);
    }

    fn on_frame_update(&mut self, fb: &mut dyn FrameBuffer) {
        self.base.on_frame_update(fb);
    }

    fn on_event(&mut self, event: &Event) -> Option<GameLoopTransition> {
        if event.event_type != event::JOY_AXIS_Y {
            return None;
        }

        let (up_idx, down_idx) = if event.device == event::DEVICE_JOY_1 {
            (self.p1_up, self.p1_down)
        } else if event.device == event::DEVICE_JOY_2 {
            (self.p2_up, self.p2_down)
        } else {
            return None;
        };

        match event.value {
            v if v == event::AXIS_UP => {
                self.base.sprites[up_idx].set_visible(true);
                self.base.sprites[down_idx].set_visible(false);
            }
            v if v == event::AXIS_DOWN => {
                self.base.sprites[up_idx].set_visible(false);
                self.base.sprites[down_idx].set_visible(true);
            }
            _ => {
                self.base.sprites[up_idx].set_visible(false);
                self.base.sprites[down_idx].set_visible(false);
            }
        }
        None
    }
}
