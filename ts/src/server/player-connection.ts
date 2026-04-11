import type { FrameEncoder } from "../frame/frame-encoder.js";

export interface PlayerConnection {
  getFrameEncoder(): FrameEncoder;
  setInputEnabled(enabled: boolean): void;
  setOutputEnabled(enabled: boolean): void;
  isInputEnabled(): boolean;
  isOutputEnabled(): boolean;
}
