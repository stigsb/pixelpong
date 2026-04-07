use std::collections::HashMap;

use crate::bitmap::{Bitmap, SimpleBitmap};
use crate::bitmap::sprite::Sprite;
use crate::server::color;

pub struct BitmapLoader {
    bitmap_paths: Vec<String>,
    cache: HashMap<String, SimpleBitmap>,
}

fn color_map() -> HashMap<u8, i8> {
    let mut m = HashMap::new();
    m.insert(b' ', color::TRANSPARENT);
    m.insert(b'.', color::BLACK);
    m.insert(b'0', color::BLACK);
    m.insert(b'#', color::WHITE);
    m.insert(b'1', color::WHITE);
    m.insert(b'2', color::RED);
    m.insert(b'3', color::CYAN);
    m.insert(b'4', color::PURPLE);
    m.insert(b'5', color::GREEN);
    m.insert(b'6', color::BLUE);
    m.insert(b'7', color::YELLOW);
    m.insert(b'8', color::ORANGE);
    m.insert(b'9', color::BROWN);
    m.insert(b'a', color::LIGHT_RED);
    m.insert(b'b', color::DARK_GREY);
    m.insert(b'c', color::GREY);
    m.insert(b'd', color::LIGHT_GREEN);
    m.insert(b'e', color::LIGHT_BLUE);
    m.insert(b'f', color::LIGHT_GREY);
    m
}

pub fn inverse_color_map() -> HashMap<i8, u8> {
    color_map().into_iter().map(|(k, v)| (v, k)).collect()
}

impl BitmapLoader {
    pub fn new(bitmap_path: &str) -> Self {
        let bitmap_paths = bitmap_path.split(':').map(String::from).collect();
        Self { bitmap_paths, cache: HashMap::new() }
    }

    pub fn load_bitmap(&mut self, name: &str) -> Result<&SimpleBitmap, String> {
        if !self.cache.contains_key(name) {
            let file = self.find_bitmap_file(name)
                .ok_or_else(|| format!("bitmap not found: {}", name))?;
            let bitmap = self.load_bitmap_from_file(&file)
                .map_err(|e| format!("error loading bitmap {}: {}", name, e))?;
            self.cache.insert(name.to_string(), bitmap);
        }
        Ok(&self.cache[name])
    }

    pub fn load_sprite(&mut self, name: &str, x: i32, y: i32) -> Result<Sprite, String> {
        let bitmap = self.load_bitmap(name)?.clone();
        Ok(Sprite::new(bitmap, x, y))
    }

    fn load_bitmap_from_file(&self, file: &str) -> Result<SimpleBitmap, Box<dyn std::error::Error>> {
        let content = std::fs::read_to_string(file)?;
        let cmap = color_map();
        let mut lines: Vec<String> = Vec::new();
        let mut width: usize = 0;

        for line in content.lines() {
            let line = line.trim_end_matches('|');
            if line.len() > width {
                width = line.len();
            }
            lines.push(line.to_string());
        }

        let height = lines.len();
        let mut pixels = vec![color::TRANSPARENT; width * height];

        for (y, line) in lines.iter().enumerate() {
            for (x, ch) in line.bytes().enumerate() {
                if x >= width { break; }
                if let Some(&color) = cmap.get(&ch) {
                    pixels[y * width + x] = color;
                }
            }
        }

        Ok(SimpleBitmap::new(width, height, pixels))
    }

    fn find_bitmap_file(&self, name: &str) -> Option<String> {
        for dir in &self.bitmap_paths {
            let path = format!("{}/{}.txt", dir, name);
            if std::path::Path::new(&path).exists() {
                return Some(path);
            }
        }
        None
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn test_load_main_game_bitmap() {
        let res_dir = std::path::Path::new(env!("CARGO_MANIFEST_DIR"))
            .parent().unwrap()
            .join("res");
        let bitmap_path = format!(
            "{}:{}",
            res_dir.join("bitmaps/47x27").display(),
            res_dir.join("sprites").display()
        );
        let mut loader = BitmapLoader::new(&bitmap_path);
        let bmp = loader.load_bitmap("main_game").expect("Failed to load main_game");
        assert_eq!(bmp.width(), 47);
        assert_eq!(bmp.height(), 27);
    }

    #[test]
    fn test_load_sprite() {
        let res_dir = std::path::Path::new(env!("CARGO_MANIFEST_DIR"))
            .parent().unwrap()
            .join("res");
        let bitmap_path = format!(
            "{}:{}",
            res_dir.join("bitmaps/47x27").display(),
            res_dir.join("sprites").display()
        );
        let mut loader = BitmapLoader::new(&bitmap_path);
        let sprite = loader.load_sprite("paddle", 5, 10).expect("Failed to load paddle sprite");
        assert_eq!(sprite.x(), 5);
        assert_eq!(sprite.y(), 10);
        assert!(sprite.is_visible());
    }
}
