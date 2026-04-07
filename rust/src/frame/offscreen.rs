use crate::frame::FrameBuffer;

pub struct OffscreenFrameBuffer {
    width: usize,
    height: usize,
    blank_frame: Vec<i8>,
    current_frame: Vec<i8>,
    bitmaps_to_render: Vec<(Vec<i8>, usize, usize, i32, i32)>, // (pixels, w, h, x, y)
}

impl OffscreenFrameBuffer {
    pub fn new(width: usize, height: usize) -> Self {
        let size = width * height;
        Self {
            width,
            height,
            blank_frame: vec![0; size],
            current_frame: vec![0; size],
            bitmaps_to_render: Vec::new(),
        }
    }

    fn render_bitmaps(&mut self) {
        let bitmaps = std::mem::take(&mut self.bitmaps_to_render);
        for (pixels, bmp_width, bmp_height, bx, by) in bitmaps {
            for row in 0..bmp_height {
                for col in 0..bmp_width {
                    let px = pixels[row * bmp_width + col];
                    if px <= 0 {
                        continue;
                    }
                    let screen_x = bx + col as i32;
                    let screen_y = by + row as i32;
                    if screen_x < 0
                        || screen_y < 0
                        || screen_x >= self.width as i32
                        || screen_y >= self.height as i32
                    {
                        continue;
                    }
                    let idx = screen_y as usize * self.width + screen_x as usize;
                    self.current_frame[idx] = px;
                }
            }
        }
    }
}

impl FrameBuffer for OffscreenFrameBuffer {
    fn width(&self) -> usize {
        self.width
    }

    fn height(&self) -> usize {
        self.height
    }

    fn get_pixel(&self, x: usize, y: usize) -> i8 {
        assert!(x < self.width, "x out of bounds: {} >= {}", x, self.width);
        assert!(y < self.height, "y out of bounds: {} >= {}", y, self.height);
        self.current_frame[y * self.width + x]
    }

    fn set_pixel(&mut self, x: usize, y: usize, color: i8) {
        assert!(x < self.width, "x out of bounds: {} >= {}", x, self.width);
        assert!(y < self.height, "y out of bounds: {} >= {}", y, self.height);
        self.current_frame[y * self.width + x] = color;
    }

    fn get_frame(&self) -> &[i8] {
        &self.current_frame
    }

    fn get_and_switch_frame(&mut self) -> Vec<i8> {
        self.render_bitmaps();
        let frame = self.current_frame.clone();
        self.current_frame = self.blank_frame.clone();
        frame
    }

    fn set_background_frame(&mut self, frame: Vec<i8>) {
        self.blank_frame = frame.clone();
        self.current_frame = frame;
    }

    fn draw_bitmap_at(
        &mut self,
        pixels: &[i8],
        bmp_width: usize,
        bmp_height: usize,
        x: i32,
        y: i32,
    ) {
        self.bitmaps_to_render
            .push((pixels.to_vec(), bmp_width, bmp_height, x, y));
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use crate::frame::FrameBuffer;

    #[test]
    fn test_new_buffer_is_blank() {
        let fb = OffscreenFrameBuffer::new(7, 3);
        let frame = fb.get_frame();
        assert_eq!(frame.len(), 21);
        assert!(frame.iter().all(|&p| p == 0));
    }

    #[test]
    fn test_set_and_get_pixel() {
        let mut fb = OffscreenFrameBuffer::new(7, 3);
        fb.set_pixel(3, 1, 5);
        assert_eq!(fb.get_pixel(3, 1), 5);
    }

    #[test]
    #[should_panic]
    fn test_get_pixel_x_out_of_bounds() {
        let fb = OffscreenFrameBuffer::new(7, 3);
        fb.get_pixel(7, 0);
    }

    #[test]
    #[should_panic]
    fn test_get_pixel_y_out_of_bounds() {
        let fb = OffscreenFrameBuffer::new(7, 3);
        fb.get_pixel(0, 3);
    }

    #[test]
    fn test_get_and_switch_frame_returns_current_and_resets() {
        let mut fb = OffscreenFrameBuffer::new(7, 3);
        fb.set_pixel(0, 0, 1);
        let frame = fb.get_and_switch_frame();
        assert_eq!(frame[0], 1);
        assert_eq!(fb.get_pixel(0, 0), 0);
    }

    #[test]
    fn test_background_frame_persists_after_switch() {
        let mut fb = OffscreenFrameBuffer::new(3, 2);
        let bg = vec![1, 0, 0, 0, 0, 1];
        fb.set_background_frame(bg);
        let _ = fb.get_and_switch_frame();
        assert_eq!(fb.get_pixel(0, 0), 1);
        assert_eq!(fb.get_pixel(2, 1), 1);
    }

    #[test]
    fn test_draw_bitmap_composites_on_get_and_switch() {
        let mut fb = OffscreenFrameBuffer::new(4, 4);
        let pixels = vec![1, 2, 3, 4];
        fb.draw_bitmap_at(&pixels, 2, 2, 1, 1);
        let frame = fb.get_and_switch_frame();
        assert_eq!(frame[(1 * 4) + 1], 1);
        assert_eq!(frame[(1 * 4) + 2], 2);
        assert_eq!(frame[(2 * 4) + 1], 3);
        assert_eq!(frame[(2 * 4) + 2], 4);
    }
}
