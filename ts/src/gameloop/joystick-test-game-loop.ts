import type { FrameBuffer } from "../frame/frame-buffer.js";
import { Event, EventType, EventValue, Device } from "../server/event.js";
import { BaseGameLoop } from "./base-game-loop.js";
import type { Container } from "../container.js";
import type { Sprite } from "../bitmap/sprite.js";

export class JoystickTestGameLoop extends BaseGameLoop {
  private readonly p1UpSprite: Sprite;
  private readonly p1DownSprite: Sprite;
  private readonly p2UpSprite: Sprite;
  private readonly p2DownSprite: Sprite;

  constructor(frameBuffer: FrameBuffer, container: Container) {
    super(frameBuffer, container);
    this.p1UpSprite = this.bitmapLoader.loadSprite("joy_up", 6, 6);
    this.p1DownSprite = this.bitmapLoader.loadSprite("joy_down", 6, 17);
    this.p2UpSprite = this.bitmapLoader.loadSprite("joy_up", 35, 6);
    this.p2DownSprite = this.bitmapLoader.loadSprite("joy_down", 35, 17);
    this.addSprite(this.p1UpSprite);
    this.addSprite(this.p1DownSprite);
    this.addSprite(this.p2UpSprite);
    this.addSprite(this.p2DownSprite);
  }

  onEnter(): void {
    super.onEnter();
    this.p1UpSprite.setVisible(false);
    this.p1DownSprite.setVisible(false);
    this.p2UpSprite.setVisible(false);
    this.p2DownSprite.setVisible(false);
  }

  onEvent(event: Event): void {
    if (event.device === Device.JOY_1 && event.eventType === EventType.JOY_AXIS_Y) {
      switch (event.value) {
        case EventValue.AXIS_UP:
          this.p1UpSprite.setVisible(true);
          break;
        case EventValue.AXIS_DOWN:
          this.p1DownSprite.setVisible(true);
          break;
        default:
          this.p1UpSprite.setVisible(false);
          this.p1DownSprite.setVisible(false);
          break;
      }
    } else if (event.device === Device.JOY_2 && event.eventType === EventType.JOY_AXIS_Y) {
      switch (event.value) {
        case EventValue.AXIS_UP:
          this.p2UpSprite.setVisible(true);
          break;
        case EventValue.AXIS_DOWN:
          this.p2DownSprite.setVisible(true);
          break;
        default:
          this.p2UpSprite.setVisible(false);
          this.p2DownSprite.setVisible(false);
          break;
      }
    }
  }
}
