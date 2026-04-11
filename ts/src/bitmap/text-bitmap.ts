import { SimpleBitmap } from "./simple-bitmap.js";
import { Font } from "./font.js";

export class TextBitmap extends SimpleBitmap {
  constructor(font: Font, text: string, color: number, spacing = 1) {
    const cw = font.getWidth();
    const ch = font.getHeight();
    const numChars = text.length;
    const fullWidth = cw * numChars + (numChars - 1) * spacing;
    const fullHeight = ch;
    const pixels = new Array(fullWidth * fullHeight).fill(0);

    for (let i = 0; i < numChars; i++) {
      const char = text.charCodeAt(i);
      const cox = (cw + spacing) * i;
      const charPixels = font.getPixelsForCharacter(char);
      for (let y = 0; y < ch; y++) {
        for (let x = 0; x < cw; x++) {
          if (charPixels[y * cw + x]) {
            pixels[fullWidth * y + cox + x] = color;
          }
        }
      }
    }

    super(fullWidth, fullHeight, pixels);
  }
}
