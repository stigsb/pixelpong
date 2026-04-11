import { readFileSync } from "node:fs";
import sharp from "sharp";
import { Font, FONT_BG, FONT_FG } from "./font.js";

interface FontMetadata {
  width: number;
  height: number;
  blankChar: string;
  pixelColor: string;
  charSpacing: [number, number];
  characterLines: string[];
}

export class FontLoader {
  constructor(private readonly fontDir: string) {}

  async loadFont(name: string): Promise<Font> {
    const pngFile = `${this.fontDir}/${name}.png`;
    const image = sharp(pngFile);
    const metadata = await image.metadata();
    const { data, info } = await image.raw().toBuffer({ resolveWithObject: true });
    const imageWidth = info.width;
    const channels = info.channels;

    const fontMeta = this.getFontMetaData(name);
    let oy = 0;
    const charPixels = fontMeta.width * fontMeta.height;
    const characterBitmaps = new Map<number, number[]>();

    let pixelR: number, pixelG: number, pixelB: number;
    const colorMatch = fontMeta.pixelColor.match(/^#([0-9a-f]{6})$/);
    if (colorMatch) {
      const colorVal = parseInt(colorMatch[1], 16);
      pixelR = (colorVal >> 16) & 0xff;
      pixelG = (colorVal >> 8) & 0xff;
      pixelB = colorVal & 0xff;
    } else {
      pixelR = pixelG = pixelB = 0;
    }

    for (const charLine of fontMeta.characterLines) {
      let ox = 0;
      for (let i = 0; i < charLine.length; i++, ox += fontMeta.width + fontMeta.charSpacing[0]) {
        if (charLine[i] === fontMeta.blankChar) {
          continue;
        }
        const pixels = new Array(charPixels).fill(FONT_BG);
        for (let y = 0; y < fontMeta.height; y++) {
          for (let x = 0; x < fontMeta.width; x++) {
            const idx = ((oy + y) * imageWidth + (ox + x)) * channels;
            const r = data[idx];
            const g = data[idx + 1];
            const b = data[idx + 2];
            if (r === pixelR && g === pixelG && b === pixelB) {
              pixels[y * fontMeta.width + x] = FONT_FG;
            }
          }
        }
        characterBitmaps.set(charLine.charCodeAt(i), pixels);
      }
      oy += fontMeta.height + fontMeta.charSpacing[1];
    }

    return new Font(characterBitmaps, fontMeta.width, fontMeta.height, fontMeta.blankChar.charCodeAt(0));
  }

  private getFontMetaData(name: string): FontMetadata {
    const charmapFile = `${this.fontDir}/${name}.json`;
    return JSON.parse(readFileSync(charmapFile, "utf-8"));
  }
}
