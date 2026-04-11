import { readFileSync, existsSync } from "node:fs";
import type { Bitmap } from "./bitmap.js";
import { SimpleBitmap } from "./simple-bitmap.js";
import { Sprite } from "./sprite.js";
import { Color } from "../server/color.js";

const colorMap: Record<string, Color> = {
  " ": Color.TRANSPARENT,
  ".": Color.BLACK,
  "0": Color.BLACK,
  "#": Color.WHITE,
  "1": Color.WHITE,
  "2": Color.RED,
  "3": Color.CYAN,
  "4": Color.PURPLE,
  "5": Color.GREEN,
  "6": Color.BLUE,
  "7": Color.YELLOW,
  "8": Color.ORANGE,
  "9": Color.BROWN,
  a: Color.LIGHT_RED,
  b: Color.DARK_GREY,
  c: Color.GREY,
  d: Color.LIGHT_GREEN,
  e: Color.LIGHT_BLUE,
  f: Color.LIGHT_GREY,
};

export { colorMap };

export class BitmapLoader {
  private bitmapCache = new Map<string, Bitmap>();
  private readonly bitmapPath: string[];

  constructor(bitmapPath: string) {
    this.bitmapPath = bitmapPath.split(":");
  }

  loadSprite(name: string, x = 0, y = 0): Sprite {
    const bitmap = this.loadBitmap(name);
    return new Sprite(bitmap, x, y);
  }

  loadBitmap(name: string): Bitmap {
    if (!this.bitmapCache.has(name)) {
      const file = this.findBitmapFileInPath(name);
      if (file) {
        this.bitmapCache.set(name, this.loadBitmapFromFile(file));
      }
    }
    const bitmap = this.bitmapCache.get(name);
    if (!bitmap) {
      throw new Error(`bitmap not found: ${name}`);
    }
    return bitmap;
  }

  private loadBitmapFromFile(file: string): Bitmap {
    const content = readFileSync(file, "utf-8");
    const lines: string[] = [];
    let width = 0;
    for (const rawLine of content.split("\n")) {
      const line = rawLine.replace(/[|\r\n]+$/, "");
      if (line.length > width) {
        width = line.length;
      }
      lines.push(line);
    }
    // Remove trailing empty line if present
    while (lines.length > 0 && lines[lines.length - 1] === "") {
      lines.pop();
    }
    const height = lines.length;
    const pixels = new Array(width * height).fill(Color.TRANSPARENT);
    for (let y = 0; y < height; y++) {
      const line = lines[y];
      const maxX = Math.min(width, line.length);
      for (let x = 0; x < maxX; x++) {
        const ch = line[x];
        if (ch in colorMap) {
          pixels[y * width + x] = colorMap[ch];
        }
      }
    }
    return new SimpleBitmap(width, height, pixels);
  }

  private findBitmapFileInPath(bitmapName: string): string | null {
    for (const dir of this.bitmapPath) {
      const txtFile = `${dir}/${bitmapName}.txt`;
      if (existsSync(txtFile)) {
        return txtFile;
      }
    }
    return null;
  }
}
