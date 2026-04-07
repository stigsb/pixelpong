package main

// Sprite wraps a Bitmap with a position and visibility flag.
type Sprite struct {
	bitmap  Bitmap
	X, Y    int
	Visible bool
}

func NewSprite(bm Bitmap, x, y int) *Sprite {
	return &Sprite{bitmap: bm, X: x, Y: y, Visible: true}
}

func (s *Sprite) MoveTo(x, y int) {
	s.X = x
	s.Y = y
}

func (s *Sprite) Bitmap() Bitmap {
	return s.bitmap
}
