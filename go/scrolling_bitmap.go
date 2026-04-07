package main

// ScrollingBitmap provides a viewport into a larger bitmap.
type ScrollingBitmap struct {
	bitmap      Bitmap
	W, H        int
	XOffset     int
	YOffset     int
	blankPixels []int
}

func NewScrollingBitmap(bm Bitmap, w, h, xOffset, yOffset int) *ScrollingBitmap {
	blank := make([]int, w*h)
	for i := range blank {
		blank[i] = ColorTransparent
	}
	return &ScrollingBitmap{
		bitmap:      bm,
		W:           w,
		H:           h,
		XOffset:     xOffset,
		YOffset:     yOffset,
		blankPixels: blank,
	}
}

func (sb *ScrollingBitmap) Width() int  { return sb.W }
func (sb *ScrollingBitmap) Height() int { return sb.H }

func (sb *ScrollingBitmap) Pixels() []int {
	origW := sb.bitmap.Width()
	origH := sb.bitmap.Height()
	origPixels := sb.bitmap.Pixels()
	pixels := make([]int, len(sb.blankPixels))
	copy(pixels, sb.blankPixels)

	maxX := sb.W
	if origW-sb.XOffset < maxX {
		maxX = origW - sb.XOffset
	}
	maxY := sb.H
	if origH-sb.YOffset < maxY {
		maxY = origH - sb.YOffset
	}
	for y := 0; y < maxY; y++ {
		oy := sb.YOffset + y
		if oy < 0 {
			continue
		}
		for x := 0; x < maxX; x++ {
			ox := sb.XOffset + x
			if ox < 0 {
				continue
			}
			pixels[y*sb.W+x] = origPixels[oy*origW+ox]
		}
	}
	return pixels
}

func (sb *ScrollingBitmap) ScrollTo(x, y int) {
	sb.XOffset = x
	sb.YOffset = y
}
