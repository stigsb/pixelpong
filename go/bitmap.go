package main

// Bitmap represents a rectangular grid of color-indexed pixels.
type Bitmap interface {
	Width() int
	Height() int
	Pixels() []int
}

// SimpleBitmap is a basic bitmap backed by a flat pixel array.
type SimpleBitmap struct {
	W, H   int
	pixels []int
}

func NewSimpleBitmap(w, h int, pixels []int) *SimpleBitmap {
	return &SimpleBitmap{W: w, H: h, pixels: pixels}
}

func (b *SimpleBitmap) Width() int    { return b.W }
func (b *SimpleBitmap) Height() int   { return b.H }
func (b *SimpleBitmap) Pixels() []int { return b.pixels }
