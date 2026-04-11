import type { FrameBuffer } from "../frame/frame-buffer.js";
import { Event, EventType, EventValue } from "../server/event.js";
import { BaseGameLoop } from "./base-game-loop.js";
import type { Container } from "../container.js";
import type { GameServer } from "../server/game-server.js";
import { MainGameLoop } from "./main-game-loop.js";

export class PressStartToPlayGameLoop extends BaseGameLoop {
  private readonly pressStartFrame: number[];
  private readonly toPlayFrame: number[];
  private enterTime = 0;
  private previousTime = 0;

  constructor(frameBuffer: FrameBuffer, container: Container) {
    super(frameBuffer, container);
    this.pressStartFrame = this.bitmapLoader.loadBitmap("press_start").getPixels();
    this.toPlayFrame = this.bitmapLoader.loadBitmap("to_play").getPixels();
  }

  onEnter(): void {
    this.frameBuffer.setBackgroundFrame(this.pressStartFrame);
    this.previousTime = 0;
    this.enterTime = Math.floor(Date.now() / 1000);
  }

  onFrameUpdate(): void {
    const elapsed = Math.floor(Date.now() / 1000) - this.enterTime;
    if (elapsed > this.previousTime) {
      switch (elapsed % 4) {
        case 0:
          this.frameBuffer.setBackgroundFrame(this.pressStartFrame);
          break;
        case 2:
          this.frameBuffer.setBackgroundFrame(this.toPlayFrame);
          break;
      }
    }
    this.previousTime = elapsed;
  }

  onEvent(event: Event): void {
    if (event.eventType === EventType.JOY_BUTTON_1 && event.value === EventValue.BUTTON_NEUTRAL) {
      const gameServer = this.container.get<GameServer>("gameServer");
      gameServer.switchToGameLoop(new MainGameLoop(this.frameBuffer, this.container));
    }
  }
}
