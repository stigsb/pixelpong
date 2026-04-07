use crate::bitmap::{Bitmap, SimpleBitmap};
use crate::bitmap::font::Font;

pub struct TextBitmap {
    inner: SimpleBitmap,
}

impl TextBitmap {
    pub fn new(font: &Font, text: &str, color: i8, spacing: usize) -> Self {
        let cw = font.width();
        let ch = font.height();
        let num_chars = text.len();
        if num_chars == 0 {
            return Self { inner: SimpleBitmap::new(0, ch, vec![]) };
        }
        let full_width = (cw * num_chars) + ((num_chars - 1) * spacing);
        let full_height = ch;
        let mut pixels = vec![0i8; full_width * full_height];

        for (i, byte) in text.bytes().enumerate() {
            let cox = (cw + spacing) * i;
            let char_pixels = font.pixels_for_character(byte);
            for y in 0..ch {
                for x in 0..cw {
                    if char_pixels[y * cw + x] != 0 {
                        pixels[full_width * y + cox + x] = color;
                    }
                }
            }
        }

        Self { inner: SimpleBitmap::new(full_width, full_height, pixels) }
    }

    pub fn into_simple_bitmap(self) -> SimpleBitmap {
        self.inner
    }
}

impl Bitmap for TextBitmap {
    fn width(&self) -> usize { self.inner.width() }
    fn height(&self) -> usize { self.inner.height() }
    fn pixels(&self) -> &[i8] { self.inner.pixels() }
}

#[cfg(test)]
mod tests {
    use super::*;
    use std::collections::HashMap;
    use crate::bitmap::font;

    fn make_test_font() -> Font {
        let mut bitmaps = HashMap::new();
        bitmaps.insert(b'A', vec![1, 1, 1, 0, 1, 1]);
        bitmaps.insert(b'B', vec![1, 0, 1, 1, 1, 0]);
        bitmaps.insert(b' ', vec![0, 0, 0, 0, 0, 0]);
        Font::new(bitmaps, 2, 3, b' ')
    }

    #[test]
    fn test_text_bitmap_dimensions() {
        let font = make_test_font();
        let tb = TextBitmap::new(&font, "AB", 5, 1);
        assert_eq!(tb.width(), 5);
        assert_eq!(tb.height(), 3);
    }

    #[test]
    fn test_text_bitmap_renders_colored() {
        let font = make_test_font();
        let tb = TextBitmap::new(&font, "A", 7, 1);
        let pixels = tb.pixels();
        assert_eq!(pixels, &[7, 7, 7, 0, 7, 7]);
    }
}
