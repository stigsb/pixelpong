import type { Bitmap } from "./bitmap.js";
import { Color } from "../server/color.js";

export class ScrollingBitmap implements Bitmap {
  private xOffset: number;
  private yOffset: number;
  private readonly blankPixels: number[];

  constructor(
    private readonly bitmap: Bitmap,
    private readonly width: number,
    private readonly height: number,
    xOffset = 0,
    yOffset = 0,
  ) {
    this.xOffset = xOffset;
    this.yOffset = yOffset;
    this.blankPixels = new Array(width * height).fill(Color.TRANSPARENT);
  }

  getOriginalBitmap(): Bitmap {
    return this.bitmap;
  }

  getWidth(): number {
    return this.width;
  }

  getHeight(): number {
    return this.height;
  }

  getPixels(): number[] {
    const origw = this.bitmap.getWidth();
    const origh = this.bitmap.getHeight();
    const origpixels = this.bitmap.getPixels();
    const pixels = [...this.blankPixels];
    const maxx = Math.min(this.width, origw - this.xOffset);
    const maxy = Math.min(this.height, origh - this.yOffset);
    for (let y = 0; y < maxy; y++) {
      const oy = this.yOffset + y;
      if (oy < 0) continue;
      for (let x = 0; x < maxx; x++) {
        const ox = this.xOffset + x;
        if (ox < 0) continue;
        pixels[y * this.width + x] = origpixels[oy * origw + ox];
      }
    }
    return pixels;
  }

  scrollTo(x: number, y: number): void {
    this.xOffset = x;
    this.yOffset = y;
  }
}
