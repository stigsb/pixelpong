import { describe, it, expect } from "vitest";
import { OffscreenFrameBuffer } from "../offscreen-frame-buffer.js";
import { JsonFrameEncoder } from "../json-frame-encoder.js";
import { Color } from "../../server/color.js";

describe("JsonFrameEncoder", () => {
  it("returns null when no pixels changed", () => {
    const fb = new OffscreenFrameBuffer(3, 2);
    const encoder = new JsonFrameEncoder(fb);
    const frame = new Array(6).fill(Color.BLACK);
    expect(encoder.encodeFrame(frame)).toBeNull();
  });

  it("sends frameDelta when few pixels changed", () => {
    const fb = new OffscreenFrameBuffer(3, 2);
    const encoder = new JsonFrameEncoder(fb);
    const frame = new Array(6).fill(Color.BLACK);
    frame[0] = Color.WHITE;
    const result = encoder.encodeFrame(frame);
    expect(result).not.toBeNull();
    const parsed = JSON.parse(result!);
    expect(parsed.frameDelta).toBeDefined();
    expect(parsed.frameDelta["0"]).toBe(Color.WHITE);
  });

  it("sends full frame when many pixels changed", () => {
    const fb = new OffscreenFrameBuffer(3, 2);
    const encoder = new JsonFrameEncoder(fb);
    const frame = new Array(6).fill(Color.WHITE);
    const result = encoder.encodeFrame(frame);
    expect(result).not.toBeNull();
    const parsed = JSON.parse(result!);
    expect(parsed.frame).toBeDefined();
  });

  it("encodeFrameInfo includes dimensions and palette", () => {
    const fb = new OffscreenFrameBuffer(47, 27);
    const encoder = new JsonFrameEncoder(fb);
    const result = JSON.parse(encoder.encodeFrameInfo(fb));
    expect(result.frameInfo.width).toBe(47);
    expect(result.frameInfo.height).toBe(27);
    expect(result.frameInfo.palette).toBeDefined();
  });
});
