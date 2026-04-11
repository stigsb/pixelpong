import type { FrameBuffer } from "./frame-buffer.js";

export interface FrameEncoder {
  encodeFrame(frame: number[]): string | null;
  encodeFrameInfo(frameBuffer: FrameBuffer): string;
}
