use crate::bitmap::{Bitmap, SimpleBitmap};
use crate::server::color;

pub struct ScrollingBitmap {
    bitmap: SimpleBitmap,
    view_width: usize,
    view_height: usize,
    x_offset: i32,
    y_offset: i32,
}

impl ScrollingBitmap {
    pub fn new(bitmap: SimpleBitmap, width: usize, height: usize, x_offset: i32, y_offset: i32) -> Self {
        Self {
            bitmap,
            view_width: width,
            view_height: height,
            x_offset,
            y_offset,
        }
    }

    pub fn scroll_to(&mut self, x: i32, y: i32) {
        self.x_offset = x;
        self.y_offset = y;
    }

    pub fn original_bitmap(&self) -> &SimpleBitmap {
        &self.bitmap
    }

    pub fn get_pixels(&self) -> Vec<i8> {
        let orig_w = self.bitmap.width() as i32;
        let orig_h = self.bitmap.height() as i32;
        let orig_pixels = self.bitmap.pixels();
        let mut pixels = vec![color::TRANSPARENT; self.view_width * self.view_height];

        let max_x = self.view_width.min((orig_w - self.x_offset) as usize);
        let max_y = self.view_height.min((orig_h - self.y_offset) as usize);

        for y in 0..max_y {
            let oy = self.y_offset + y as i32;
            if oy < 0 { continue; }
            for x in 0..max_x {
                let ox = self.x_offset + x as i32;
                if ox < 0 { continue; }
                pixels[y * self.view_width + x] = orig_pixels[(oy as usize) * (orig_w as usize) + (ox as usize)];
            }
        }
        pixels
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn test_scrolling_bitmap_viewport() {
        let pixels = vec![
            0, 1, 2, 3,
            4, 5, 6, 7,
            8, 9, 10, 11,
            12, 13, 14, 15,
        ];
        let bmp = SimpleBitmap::new(4, 4, pixels);
        let sb = ScrollingBitmap::new(bmp, 2, 2, 1, 1);
        let view = sb.get_pixels();
        assert_eq!(view, vec![5, 6, 9, 10]);
    }

    #[test]
    fn test_scroll_to() {
        let pixels = vec![0, 1, 2, 3, 4, 5, 6, 7, 8];
        let bmp = SimpleBitmap::new(3, 3, pixels);
        let mut sb = ScrollingBitmap::new(bmp, 2, 2, 0, 0);
        assert_eq!(sb.get_pixels(), vec![0, 1, 3, 4]);
        sb.scroll_to(1, 1);
        assert_eq!(sb.get_pixels(), vec![4, 5, 7, 8]);
    }
}
