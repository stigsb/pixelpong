use std::collections::HashMap;

pub const BG: i8 = 0;
pub const FG: i8 = 1;

pub struct Font {
    character_bitmaps: HashMap<u8, Vec<i8>>,
    width: usize,
    height: usize,
    blank_char: u8,
}

impl Font {
    pub fn new(character_bitmaps: HashMap<u8, Vec<i8>>, width: usize, height: usize, blank_char: u8) -> Self {
        Self { character_bitmaps, width, height, blank_char }
    }

    pub fn pixels_for_character(&self, ch: u8) -> &[i8] {
        self.character_bitmaps
            .get(&ch)
            .or_else(|| self.character_bitmaps.get(&self.blank_char))
            .map(|v| v.as_slice())
            .unwrap_or(&[])
    }

    pub fn width(&self) -> usize { self.width }
    pub fn height(&self) -> usize { self.height }
}

#[derive(serde::Deserialize)]
#[serde(rename_all = "camelCase")]
struct FontMeta {
    width: usize,
    height: usize,
    blank_char: String,
    pixel_color: String,
    char_spacing: [usize; 2],
    character_lines: Vec<String>,
}

pub struct FontLoader {
    font_dir: String,
}

impl FontLoader {
    pub fn new(font_dir: &str) -> Self {
        Self { font_dir: font_dir.to_string() }
    }

    pub fn load_font(&self, name: &str) -> Result<Font, Box<dyn std::error::Error>> {
        let json_path = format!("{}/{}.json", self.font_dir, name);
        let png_path = format!("{}/{}.png", self.font_dir, name);

        let json_data = std::fs::read_to_string(&json_path)?;
        let meta: FontMeta = serde_json::from_str(&json_data)?;

        let pixel_color = parse_hex_color(&meta.pixel_color)?;

        let decoder = png::Decoder::new(std::fs::File::open(&png_path)?);
        let mut reader = decoder.read_info()?;
        let mut buf = vec![0u8; reader.output_buffer_size()];
        let info = reader.next_frame(&mut buf)?;
        let img_width = info.width as usize;
        let img_data = &buf[..info.buffer_size()];
        let bytes_per_pixel = match info.color_type {
            png::ColorType::Rgb => 3,
            png::ColorType::Rgba => 4,
            png::ColorType::Grayscale => 1,
            png::ColorType::GrayscaleAlpha => 2,
            png::ColorType::Indexed => 1,
            _ => return Err("Unsupported PNG color type".into()),
        };

        let blank_char = meta.blank_char.bytes().next().unwrap_or(b' ');
        let char_pixels = meta.width * meta.height;
        let mut character_bitmaps: HashMap<u8, Vec<i8>> = HashMap::new();

        let mut oy: usize = 0;
        for char_line in &meta.character_lines {
            let mut ox: usize = 0;
            for ch in char_line.bytes() {
                if ch == blank_char {
                    ox += meta.width + meta.char_spacing[0];
                    continue;
                }
                let mut pixels = vec![BG; char_pixels];
                for y in 0..meta.height {
                    for x in 0..meta.width {
                        let px = ox + x;
                        let py = oy + y;
                        if pixel_matches(img_data, img_width, bytes_per_pixel, px, py, pixel_color) {
                            pixels[y * meta.width + x] = FG;
                        }
                    }
                }
                character_bitmaps.insert(ch, pixels);
                ox += meta.width + meta.char_spacing[0];
            }
            oy += meta.height + meta.char_spacing[1];
        }

        Ok(Font::new(character_bitmaps, meta.width, meta.height, blank_char))
    }
}

fn parse_hex_color(s: &str) -> Result<u32, Box<dyn std::error::Error>> {
    let s = s.trim_start_matches('#');
    Ok(u32::from_str_radix(s, 16)?)
}

fn pixel_matches(data: &[u8], img_width: usize, bpp: usize, x: usize, y: usize, target: u32) -> bool {
    let idx = (y * img_width + x) * bpp;
    if idx + bpp > data.len() {
        return false;
    }
    match bpp {
        3 | 4 => {
            let r = data[idx] as u32;
            let g = data[idx + 1] as u32;
            let b = data[idx + 2] as u32;
            let color = (r << 16) | (g << 8) | b;
            color == target
        }
        1 => {
            data[idx] != 0
        }
        2 => {
            let gray = data[idx] as u32;
            let color = (gray << 16) | (gray << 8) | gray;
            color == target
        }
        _ => false,
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn test_font_loader_loads_5x7() {
        let res_dir = std::path::Path::new(env!("CARGO_MANIFEST_DIR"))
            .parent().unwrap()
            .join("res/fonts");
        if !res_dir.exists() {
            eprintln!("Skipping test: res/fonts not found");
            return;
        }
        let loader = FontLoader::new(res_dir.to_str().unwrap());
        let font = loader.load_font("5x7").expect("Failed to load 5x7 font");
        assert_eq!(font.width(), 5);
        assert_eq!(font.height(), 7);
        let a_pixels = font.pixels_for_character(b'A');
        assert_eq!(a_pixels.len(), 35);
        assert!(a_pixels.iter().any(|&p| p == FG));
    }
}
