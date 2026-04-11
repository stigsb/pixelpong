import type { Bitmap } from "./bitmap.js";

export class SimpleBitmap implements Bitmap {
  constructor(
    protected readonly width: number,
    protected readonly height: number,
    protected readonly pixels: number[],
  ) {}

  getWidth(): number {
    return this.width;
  }

  getHeight(): number {
    return this.height;
  }

  getPixels(): number[] {
    return this.pixels;
  }
}
