import type { FrameEncoder } from "../frame/frame-encoder.js";
import type { PlayerConnection } from "./player-connection.js";

export class ActivePlayerConnection implements PlayerConnection {
  private inputEnabled = false;
  private outputEnabled = false;

  constructor(private readonly frameEncoder: FrameEncoder) {}

  getFrameEncoder(): FrameEncoder {
    return this.frameEncoder;
  }

  setInputEnabled(enabled: boolean): void {
    this.inputEnabled = enabled;
  }

  setOutputEnabled(enabled: boolean): void {
    this.outputEnabled = enabled;
  }

  isInputEnabled(): boolean {
    return this.inputEnabled;
  }

  isOutputEnabled(): boolean {
    return this.outputEnabled;
  }
}
