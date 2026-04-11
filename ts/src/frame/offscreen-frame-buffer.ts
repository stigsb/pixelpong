import type { Bitmap } from "../bitmap/bitmap.js";
import { Color } from "../server/color.js";
import type { FrameBuffer } from "./frame-buffer.js";

export class OffscreenFrameBuffer implements FrameBuffer {
  private blankFrame: number[];
  private currentFrame: number[];
  private readonly frameBufferSize: number;
  private bitmapsToRender: [Bitmap, number, number][] = [];

  constructor(
    private readonly width: number,
    private readonly height: number,
  ) {
    this.frameBufferSize = width * height;
    this.blankFrame = new Array(this.frameBufferSize).fill(0);
    this.currentFrame = [...this.blankFrame];
  }

  getWidth(): number {
    return this.width;
  }

  getHeight(): number {
    return this.height;
  }

  getPixel(x: number, y: number): number {
    if (x < 0 || x >= this.width || y < 0 || y >= this.height) {
      throw new RangeError("x or y out of bounds");
    }
    return this.currentFrame[y * this.width + x];
  }

  setPixel(x: number, y: number, color: number): void {
    if (x < 0 || x >= this.width || y < 0 || y >= this.height) {
      throw new RangeError("x or y out of bounds");
    }
    this.currentFrame[y * this.width + x] = color;
  }

  getAndSwitchFrame(): number[] {
    this.renderBitmaps();
    const frame = this.currentFrame;
    this.newFrame();
    return frame;
  }

  getFrame(): number[] {
    return this.currentFrame;
  }

  setBackgroundFrame(frame: number[]): void {
    this.blankFrame = frame;
  }

  drawBitmapAt(bitmap: Bitmap, x: number, y: number): void {
    this.bitmapsToRender.push([bitmap, x, y]);
  }

  private newFrame(): void {
    this.currentFrame = [...this.blankFrame];
    this.bitmapsToRender = [];
  }

  private renderBitmaps(): void {
    for (const [sprite, xoff, yoff] of this.bitmapsToRender) {
      const pixels = sprite.getPixels();
      const w = sprite.getWidth();
      const h = sprite.getHeight();
      for (let x = 0; x < w; x++) {
        const xx = xoff + x;
        if (xx >= this.width || xx < 0) continue;
        for (let y = 0; y < h; y++) {
          const yy = yoff + y;
          if (yy >= this.height || yy < 0) continue;
          const pixel = pixels[y * w + x];
          if (pixel !== Color.TRANSPARENT) {
            this.currentFrame[yy * this.width + xx] = pixel;
          }
        }
      }
    }
  }
}
