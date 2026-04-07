pub mod font;
pub mod loader;
pub mod scrolling;
pub mod sprite;
pub mod text_bitmap;

use crate::server::color;

pub trait Bitmap {
    fn width(&self) -> usize;
    fn height(&self) -> usize;
    fn pixels(&self) -> &[i8];
}

#[derive(Clone)]
pub struct SimpleBitmap {
    width: usize,
    height: usize,
    pixels: Vec<i8>,
}

impl SimpleBitmap {
    pub fn new(width: usize, height: usize, pixels: Vec<i8>) -> Self {
        Self { width, height, pixels }
    }
}

impl Bitmap for SimpleBitmap {
    fn width(&self) -> usize { self.width }
    fn height(&self) -> usize { self.height }
    fn pixels(&self) -> &[i8] { &self.pixels }
}
