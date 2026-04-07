use crate::bitmap::SimpleBitmap;

pub struct Sprite {
    bitmap: SimpleBitmap,
    x: i32,
    y: i32,
    visible: bool,
}

impl Sprite {
    pub fn new(bitmap: SimpleBitmap, x: i32, y: i32) -> Self {
        Self { bitmap, x, y, visible: true }
    }

    pub fn move_to(&mut self, x: i32, y: i32) {
        self.x = x;
        self.y = y;
    }

    pub fn set_visible(&mut self, visible: bool) {
        self.visible = visible;
    }

    pub fn is_visible(&self) -> bool { self.visible }
    pub fn x(&self) -> i32 { self.x }
    pub fn y(&self) -> i32 { self.y }
    pub fn bitmap(&self) -> &SimpleBitmap { &self.bitmap }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn test_sprite_position_and_visibility() {
        let bmp = SimpleBitmap::new(2, 2, vec![1, 1, 1, 1]);
        let mut sprite = Sprite::new(bmp, 5, 10);
        assert_eq!(sprite.x(), 5);
        assert_eq!(sprite.y(), 10);
        assert!(sprite.is_visible());

        sprite.move_to(3, 7);
        assert_eq!(sprite.x(), 3);
        assert_eq!(sprite.y(), 7);

        sprite.set_visible(false);
        assert!(!sprite.is_visible());
    }
}
