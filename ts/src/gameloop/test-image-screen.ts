import type { FrameBuffer } from "../frame/frame-buffer.js";
import { Event, EventType, EventValue } from "../server/event.js";
import { BaseGameLoop } from "./base-game-loop.js";
import type { Container } from "../container.js";
import type { GameServer } from "../server/game-server.js";
import { PressStartToPlayGameLoop } from "./press-start-to-play-game-loop.js";

export class TestImageScreen extends BaseGameLoop {
  constructor(frameBuffer: FrameBuffer, container: Container) {
    super(frameBuffer, container);
    this.background = this.bitmapLoader.loadBitmap("test_image");
  }

  onEvent(event: Event): void {
    if (event.eventType === EventType.JOY_BUTTON_1 && event.value === EventValue.BUTTON_NEUTRAL) {
      const gameServer = this.container.get<GameServer>("gameServer");
      gameServer.switchToGameLoop(new PressStartToPlayGameLoop(this.frameBuffer, this.container));
    }
  }
}
