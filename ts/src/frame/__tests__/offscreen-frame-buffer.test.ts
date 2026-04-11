import { describe, it, expect } from "vitest";
import { OffscreenFrameBuffer } from "../offscreen-frame-buffer.js";
import { SimpleBitmap } from "../../bitmap/simple-bitmap.js";

describe("OffscreenFrameBuffer", () => {
  it("initializes with correct dimensions", () => {
    const fb = new OffscreenFrameBuffer(10, 5);
    expect(fb.getWidth()).toBe(10);
    expect(fb.getHeight()).toBe(5);
  });

  it("get/set pixel", () => {
    const fb = new OffscreenFrameBuffer(10, 5);
    fb.setPixel(3, 2, 7);
    expect(fb.getPixel(3, 2)).toBe(7);
  });

  it("throws on out of bounds", () => {
    const fb = new OffscreenFrameBuffer(10, 5);
    expect(() => fb.setPixel(-1, 0, 1)).toThrow();
    expect(() => fb.setPixel(10, 0, 1)).toThrow();
    expect(() => fb.setPixel(0, -1, 1)).toThrow();
    expect(() => fb.setPixel(0, 5, 1)).toThrow();
  });

  it("getAndSwitchFrame resets to background", () => {
    const fb = new OffscreenFrameBuffer(3, 2);
    fb.setPixel(1, 1, 5);
    const frame = fb.getAndSwitchFrame();
    expect(frame[3 * 1 + 1]).toBe(5);
    // After switch, pixel should be back to 0
    expect(fb.getPixel(1, 1)).toBe(0);
  });

  it("drawBitmapAt renders bitmap on getAndSwitchFrame", () => {
    const fb = new OffscreenFrameBuffer(5, 5);
    const bitmap = new SimpleBitmap(2, 2, [1, 2, 3, 4]);
    fb.drawBitmapAt(bitmap, 1, 1);
    const frame = fb.getAndSwitchFrame();
    expect(frame[5 * 1 + 1]).toBe(1);
    expect(frame[5 * 1 + 2]).toBe(2);
    expect(frame[5 * 2 + 1]).toBe(3);
    expect(frame[5 * 2 + 2]).toBe(4);
  });

  it("setBackgroundFrame persists across frame switches", () => {
    const fb = new OffscreenFrameBuffer(3, 2);
    const bg = new Array(6).fill(0);
    bg[0] = 7;
    fb.setBackgroundFrame(bg);
    fb.getAndSwitchFrame(); // switch to new frame with bg
    expect(fb.getPixel(0, 0)).toBe(7);
  });
});
