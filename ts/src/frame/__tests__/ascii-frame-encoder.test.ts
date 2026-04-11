import { describe, it, expect } from "vitest";
import { OffscreenFrameBuffer } from "../offscreen-frame-buffer.js";
import { AsciiFrameEncoder } from "../ascii-frame-encoder.js";
import { Color } from "../../server/color.js";

describe("AsciiFrameEncoder", () => {
  it("encodes a blank frame as dots", () => {
    const fb = new OffscreenFrameBuffer(3, 2);
    const encoder = new AsciiFrameEncoder(fb);
    const frame = new Array(6).fill(Color.TRANSPARENT);
    const result = encoder.encodeFrame(frame);
    expect(result).toBe("...\n...");
  });

  it("encodes colored pixels with the right characters", () => {
    const fb = new OffscreenFrameBuffer(3, 1);
    const encoder = new AsciiFrameEncoder(fb);
    const frame = [Color.BLACK, Color.WHITE, Color.RED];
    const result = encoder.encodeFrame(frame);
    expect(result).toBe(".#2");
  });
});
