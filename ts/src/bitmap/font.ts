export const FONT_BG = 0;
export const FONT_FG = 1;

export class Font {
  constructor(
    private readonly characterBitmaps: Map<number, number[]>,
    private readonly width: number,
    private readonly height: number,
    private readonly blankChar: number,
  ) {}

  getPixelsForCharacter(char: number): number[] {
    return this.characterBitmaps.get(char) ?? this.characterBitmaps.get(this.blankChar) ?? [];
  }

  getWidth(): number {
    return this.width;
  }

  getHeight(): number {
    return this.height;
  }
}
