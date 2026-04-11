import type { Bitmap } from "./bitmap.js";

export class Sprite {
  private x: number;
  private y: number;
  private visible: boolean;

  constructor(
    private readonly bitmap: Bitmap,
    xpos = 0,
    ypos = 0,
    visible = true,
  ) {
    this.x = xpos;
    this.y = ypos;
    this.visible = visible;
  }

  moveTo(x: number, y: number): void {
    this.x = x;
    this.y = y;
  }

  setVisible(visible: boolean): this {
    this.visible = visible;
    return this;
  }

  getX(): number {
    return this.x;
  }

  getY(): number {
    return this.y;
  }

  isVisible(): boolean {
    return this.visible;
  }

  getBitmap(): Bitmap {
    return this.bitmap;
  }
}
