pub mod ascii_encoder;
pub mod json_encoder;
pub mod offscreen;

pub trait FrameBuffer {
    fn width(&self) -> usize;
    fn height(&self) -> usize;
    fn get_pixel(&self, x: usize, y: usize) -> i8;
    fn set_pixel(&mut self, x: usize, y: usize, color: i8);
    fn get_frame(&self) -> &[i8];
    fn get_and_switch_frame(&mut self) -> Vec<i8>;
    fn set_background_frame(&mut self, frame: Vec<i8>);
    fn draw_bitmap_at(&mut self, pixels: &[i8], bmp_width: usize, bmp_height: usize, x: i32, y: i32);
}

pub trait FrameEncoder {
    fn encode_frame(&mut self, frame: &[i8]) -> Option<String>;
    fn encode_frame_info(&self, width: usize, height: usize) -> String;
}
