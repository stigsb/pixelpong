package main

// FrameBuffer is a double-buffered pixel grid that supports sprite rendering.
type FrameBuffer struct {
	width, height int
	background    []int
	current       []int
	bitmaps       []bitmapDraw
}

type bitmapDraw struct {
	bm   Bitmap
	x, y int
}

func NewFrameBuffer(width, height int) *FrameBuffer {
	size := width * height
	bg := make([]int, size)
	cur := make([]int, size)
	return &FrameBuffer{
		width:      width,
		height:     height,
		background: bg,
		current:    cur,
	}
}

func (fb *FrameBuffer) Width() int  { return fb.width }
func (fb *FrameBuffer) Height() int { return fb.height }

func (fb *FrameBuffer) GetPixel(x, y int) int {
	return fb.current[y*fb.width+x]
}

func (fb *FrameBuffer) SetPixel(x, y, color int) {
	if x < 0 || x >= fb.width || y < 0 || y >= fb.height {
		return
	}
	fb.current[y*fb.width+x] = color
}

func (fb *FrameBuffer) Frame() []int {
	return fb.current
}

func (fb *FrameBuffer) GetAndSwitchFrame() []int {
	fb.renderBitmaps()
	frame := fb.current
	fb.newFrame()
	return frame
}

func (fb *FrameBuffer) SetBackgroundFrame(frame []int) {
	fb.background = make([]int, len(frame))
	copy(fb.background, frame)
}

func (fb *FrameBuffer) DrawBitmapAt(bm Bitmap, x, y int) {
	fb.bitmaps = append(fb.bitmaps, bitmapDraw{bm: bm, x: x, y: y})
}

func (fb *FrameBuffer) newFrame() {
	fb.current = make([]int, fb.width*fb.height)
	copy(fb.current, fb.background)
	fb.bitmaps = fb.bitmaps[:0]
}

func (fb *FrameBuffer) renderBitmaps() {
	for _, bd := range fb.bitmaps {
		pixels := bd.bm.Pixels()
		w := bd.bm.Width()
		h := bd.bm.Height()
		for x := 0; x < w; x++ {
			xx := bd.x + x
			if xx < 0 || xx >= fb.width {
				continue
			}
			for y := 0; y < h; y++ {
				yy := bd.y + y
				if yy < 0 || yy >= fb.height {
					continue
				}
				pixel := pixels[y*w+x]
				if pixel == ColorTransparent {
					continue
				}
				fb.SetPixel(xx, yy, pixel)
			}
		}
	}
}
