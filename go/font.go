package main

// Font is a fixed-width monochrome pixel font.
type Font struct {
	CharBitmaps map[int][]int // character code -> pixel array
	W           int           // character width
	H           int           // character height
	BlankChar   int           // character code for unknown chars
}

const (
	FontBG = 0
	FontFG = 1
)

func (f *Font) PixelsForChar(charCode int) []int {
	if px, ok := f.CharBitmaps[charCode]; ok {
		return px
	}
	return f.CharBitmaps[f.BlankChar]
}

func (f *Font) Width() int  { return f.W }
func (f *Font) Height() int { return f.H }
