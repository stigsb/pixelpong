package main

import "testing"

func TestFrameBufferNew(t *testing.T) {
	fb := NewFrameBuffer(7, 3)
	if fb.Width() != 7 {
		t.Errorf("expected width 7, got %d", fb.Width())
	}
	if fb.Height() != 3 {
		t.Errorf("expected height 3, got %d", fb.Height())
	}
	frame := fb.Frame()
	for i, p := range frame {
		if p != 0 {
			t.Errorf("expected pixel %d to be 0, got %d", i, p)
		}
	}
}

func TestFrameBufferSetGetPixel(t *testing.T) {
	fb := NewFrameBuffer(7, 3)
	fb.SetPixel(1, 2, 1)
	if fb.GetPixel(1, 2) != 1 {
		t.Error("expected pixel (1,2) to be 1")
	}
	if fb.GetPixel(2, 1) != 0 {
		t.Error("expected pixel (2,1) to be 0")
	}
}

func TestFrameBufferGetAndSwitch(t *testing.T) {
	fb := NewFrameBuffer(7, 3)
	fb.SetPixel(0, 0, 1)
	frame := fb.GetAndSwitchFrame()
	if frame[0] != 1 {
		t.Error("expected pixel 0 to be 1 in returned frame")
	}
	if fb.Frame()[0] != 0 {
		t.Error("expected pixel 0 to be 0 after switch")
	}
}

func TestFrameBufferDrawBitmapAt(t *testing.T) {
	fb := NewFrameBuffer(4, 4)
	bm := NewSimpleBitmap(2, 2, []int{1, 2, 3, 4})
	fb.DrawBitmapAt(bm, 1, 1)
	frame := fb.GetAndSwitchFrame()
	// Pixel at (1,1) should be 1
	if frame[1*4+1] != 1 {
		t.Errorf("expected pixel at (1,1) to be 1, got %d", frame[1*4+1])
	}
	// Pixel at (2,2) should be 4
	if frame[2*4+2] != 4 {
		t.Errorf("expected pixel at (2,2) to be 4, got %d", frame[2*4+2])
	}
}

func TestFrameBufferSetBackground(t *testing.T) {
	fb := NewFrameBuffer(3, 1)
	bg := []int{5, 5, 5}
	fb.SetBackgroundFrame(bg)
	fb.SetPixel(0, 0, 1)
	_ = fb.GetAndSwitchFrame()
	// After switch, should revert to background
	frame := fb.Frame()
	if frame[0] != 5 {
		t.Errorf("expected pixel 0 to be 5 (background), got %d", frame[0])
	}
}
