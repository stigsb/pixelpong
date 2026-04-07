pub mod base;
pub mod joystick_test;
pub mod main_game;
pub mod maker_faire;
pub mod press_start;
pub mod test_image;

use crate::bitmap::loader::BitmapLoader;
use crate::frame::FrameBuffer;
use crate::server::event::Event;

pub enum GameLoopTransition {
    SwitchTo(Box<dyn GameLoop>),
}

pub trait GameLoop {
    fn on_enter(&mut self, fb: &mut dyn FrameBuffer, loader: &mut BitmapLoader);
    fn on_frame_update(&mut self, fb: &mut dyn FrameBuffer);
    fn on_event(&mut self, event: &Event) -> Option<GameLoopTransition>;
}
