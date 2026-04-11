import type { Bitmap } from "../bitmap/bitmap.js";

export interface FrameBuffer {
  getWidth(): number;
  getHeight(): number;
  getPixel(x: number, y: number): number;
  setPixel(x: number, y: number, color: number): void;
  getFrame(): number[];
  getAndSwitchFrame(): number[];
  setBackgroundFrame(frame: number[]): void;
  drawBitmapAt(bitmap: Bitmap, x: number, y: number): void;
}
