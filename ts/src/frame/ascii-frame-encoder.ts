import { colorMap } from "../bitmap/bitmap-loader.js";
import { Color } from "../server/color.js";
import type { FrameBuffer } from "./frame-buffer.js";
import type { FrameEncoder } from "./frame-encoder.js";

export class AsciiFrameEncoder implements FrameEncoder {
  private readonly width: number;
  private readonly height: number;
  private readonly blankEncodedFrame: string;
  private readonly colorCharMap: Map<number, string>;

  constructor(frameBuffer: FrameBuffer) {
    this.width = frameBuffer.getWidth();
    this.height = frameBuffer.getHeight();
    const encodedSize = this.height * (this.width + 1) - 1;
    const chars = new Array(encodedSize).fill(".");
    for (let i = this.width; i < encodedSize; i += this.width + 1) {
      chars[i] = "\n";
    }
    this.blankEncodedFrame = chars.join("");

    this.colorCharMap = new Map<number, string>();
    for (const [ch, color] of Object.entries(colorMap)) {
      this.colorCharMap.set(color, ch);
    }
  }

  encodeFrame(frame: number[]): string {
    const chars = [...this.blankEncodedFrame];
    for (let y = 0; y < this.height; y++) {
      for (let x = 0; x < this.width; x++) {
        const color = frame[this.width * y + x];
        if (color === Color.TRANSPARENT) continue;
        const idx = (this.width + 1) * y + x;
        chars[idx] = this.colorCharMap.get(color) ?? " ";
      }
    }
    return chars.join("");
  }

  encodeFrameInfo(_frameBuffer: FrameBuffer): string {
    return "";
  }
}
