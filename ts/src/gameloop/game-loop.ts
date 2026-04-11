import type { Event } from "../server/event.js";

export interface GameLoop {
  onEnter(): void;
  onFrameUpdate(): void;
  onEvent(event: Event): void;
}
