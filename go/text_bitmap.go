package main

// NewTextBitmap creates a bitmap that renders the given text string using the font.
func NewTextBitmap(font *Font, text string, color int, spacing int) *SimpleBitmap {
	cw := font.W
	ch := font.H
	numChars := len(text)
	if numChars == 0 {
		return NewSimpleBitmap(0, ch, nil)
	}
	fullWidth := (cw * numChars) + ((numChars - 1) * spacing)
	fullHeight := ch
	pixels := make([]int, fullWidth*fullHeight)

	for i := 0; i < numChars; i++ {
		charCode := int(text[i])
		cox := (cw + spacing) * i
		charPixels := font.PixelsForChar(charCode)
		for y := 0; y < ch; y++ {
			for x := 0; x < cw; x++ {
				if charPixels[y*cw+x] != 0 {
					pixels[fullWidth*y+cox+x] = color
				}
			}
		}
	}
	return NewSimpleBitmap(fullWidth, fullHeight, pixels)
}
