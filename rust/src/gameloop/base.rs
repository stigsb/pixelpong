use crate::bitmap::{Bitmap, SimpleBitmap};
use crate::bitmap::sprite::Sprite;
use crate::frame::FrameBuffer;

pub struct BaseGameLoop {
    pub background: Option<SimpleBitmap>,
    pub sprites: Vec<Sprite>,
}

impl BaseGameLoop {
    pub fn new() -> Self {
        Self {
            background: None,
            sprites: Vec::new(),
        }
    }

    pub fn on_enter(&self, fb: &mut dyn FrameBuffer) {
        if let Some(ref bg) = self.background {
            fb.set_background_frame(bg.pixels().to_vec());
        }
    }

    pub fn add_sprite(&mut self, sprite: Sprite) -> usize {
        self.sprites.push(sprite);
        self.sprites.len() - 1
    }

    pub fn render_visible_sprites(&self, fb: &mut dyn FrameBuffer) {
        for sprite in &self.sprites {
            if sprite.is_visible() {
                let bmp = sprite.bitmap();
                fb.draw_bitmap_at(bmp.pixels(), bmp.width(), bmp.height(), sprite.x(), sprite.y());
            }
        }
    }

    pub fn on_frame_update(&self, fb: &mut dyn FrameBuffer) {
        self.render_visible_sprites(fb);
    }
}
