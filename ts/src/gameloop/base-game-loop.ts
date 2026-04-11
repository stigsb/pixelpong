import type { Bitmap } from "../bitmap/bitmap.js";
import { BitmapLoader } from "../bitmap/bitmap-loader.js";
import { Sprite } from "../bitmap/sprite.js";
import type { FrameBuffer } from "../frame/frame-buffer.js";
import type { Event } from "../server/event.js";
import type { GameLoop } from "./game-loop.js";
import type { Container } from "../container.js";

export abstract class BaseGameLoop implements GameLoop {
  protected readonly frameBuffer: FrameBuffer;
  protected readonly bitmapLoader: BitmapLoader;
  protected background: Bitmap | null = null;
  protected sprites: Sprite[] = [];
  protected readonly container: Container;

  constructor(frameBuffer: FrameBuffer, container: Container) {
    this.frameBuffer = frameBuffer;
    this.container = container;
    this.bitmapLoader = container.get<BitmapLoader>("bitmapLoader");
  }

  onEnter(): void {
    if (this.background) {
      this.frameBuffer.setBackgroundFrame(this.background.getPixels());
    }
  }

  onFrameUpdate(): void {
    this.renderVisibleSprites();
  }

  abstract onEvent(event: Event): void;

  addSprite(sprite: Sprite): void {
    this.sprites.push(sprite);
  }

  renderVisibleSprites(): void {
    for (const sprite of this.sprites) {
      if (sprite.isVisible()) {
        this.frameBuffer.drawBitmapAt(sprite.getBitmap(), sprite.getX(), sprite.getY());
      }
    }
  }
}
