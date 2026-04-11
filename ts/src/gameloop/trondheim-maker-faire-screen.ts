import type { BitmapLoader } from "../bitmap/bitmap-loader.js";
import type { FrameBuffer } from "../frame/frame-buffer.js";
import type { Event } from "../server/event.js";
import type { GameLoop } from "./game-loop.js";

export class TrondheimMakerFaireScreen implements GameLoop {
  private readonly frames: number[][] = [];
  private previousTime = 0;
  private currentFrameIndex = 0;

  constructor(
    private readonly frameBuffer: FrameBuffer,
    bitmapLoader: BitmapLoader,
  ) {
    for (const bitmapName of ["trondheim", "maker", "faire"]) {
      this.frames.push(bitmapLoader.loadBitmap(bitmapName).getPixels());
    }
  }

  onEnter(): void {
    this.currentFrameIndex = 0;
    this.frameBuffer.setBackgroundFrame(this.frames[0]);
    this.previousTime = Math.floor(Date.now() / 1000);
  }

  onFrameUpdate(): void {
    const now = Math.floor(Date.now() / 1000);
    if (now > this.previousTime) {
      this.currentFrameIndex = (this.currentFrameIndex + 1) % this.frames.length;
      this.frameBuffer.setBackgroundFrame(this.frames[this.currentFrameIndex]);
    }
    this.previousTime = now;
  }

  onEvent(_event: Event): void {
    // not implemented
  }
}
