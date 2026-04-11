import { Color, getPalette } from "../server/color.js";
import type { FrameBuffer } from "./frame-buffer.js";
import type { FrameEncoder } from "./frame-encoder.js";

export class JsonFrameEncoder implements FrameEncoder {
  private readonly width: number;
  private readonly height: number;
  private previousFrame: number[];

  constructor(frameBuffer: FrameBuffer) {
    this.width = frameBuffer.getWidth();
    this.height = frameBuffer.getHeight();
    const size = this.width * this.height;
    this.previousFrame = new Array(size).fill(Color.BLACK);
  }

  encodeFrame(frame: number[]): string | null {
    const diff: Record<string, number> = {};
    let diffCount = 0;
    for (let i = 0; i < frame.length; i++) {
      if (frame[i] !== this.previousFrame[i]) {
        diff[String(i)] = frame[i];
        diffCount++;
      }
    }
    this.previousFrame = [...frame];

    if (diffCount === 0) {
      return null;
    }

    if (diffCount < frame.length / 3) {
      return JSON.stringify({ frameDelta: diff });
    }

    const pixels: Record<string, number> = {};
    for (let ix = 0; ix < frame.length; ix++) {
      if (frame[ix] !== Color.BLACK) {
        pixels[String(ix)] = frame[ix];
      }
    }
    return JSON.stringify({ frame: pixels });
  }

  encodeFrameInfo(frameBuffer: FrameBuffer): string {
    return JSON.stringify({
      frameInfo: {
        width: frameBuffer.getWidth(),
        height: frameBuffer.getHeight(),
        palette: getPalette(),
      },
    });
  }
}
