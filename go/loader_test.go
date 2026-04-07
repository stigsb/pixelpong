package main

import "testing"

func TestParseBitmapData(t *testing.T) {
	data := []byte(".#.\n#.#\n.#.")
	bm := parseBitmapData(data)
	if bm.Width() != 3 {
		t.Errorf("expected width 3, got %d", bm.Width())
	}
	if bm.Height() != 3 {
		t.Errorf("expected height 3, got %d", bm.Height())
	}
	// '#' maps to White (1), '.' maps to Black (0)
	pixels := bm.Pixels()
	if pixels[0] != ColorBlack {
		t.Errorf("expected pixel 0 to be Black, got %d", pixels[0])
	}
	if pixels[1] != ColorWhite {
		t.Errorf("expected pixel 1 to be White, got %d", pixels[1])
	}
}

func TestBitmapLoaderLoadBitmap(t *testing.T) {
	loader := NewBitmapLoader([]string{"res/sprites"})
	bm, err := loader.LoadBitmap("ball")
	if err != nil {
		t.Fatalf("failed to load ball bitmap: %v", err)
	}
	if bm.Width() == 0 || bm.Height() == 0 {
		t.Errorf("expected non-zero dimensions, got %dx%d", bm.Width(), bm.Height())
	}
}

func TestBitmapLoaderCaching(t *testing.T) {
	loader := NewBitmapLoader([]string{"res/sprites"})
	bm1, _ := loader.LoadBitmap("ball")
	bm2, _ := loader.LoadBitmap("ball")
	if bm1 != bm2 {
		t.Error("expected same pointer from cache")
	}
}

func TestBitmapLoaderNotFound(t *testing.T) {
	loader := NewBitmapLoader([]string{"res/sprites"})
	_, err := loader.LoadBitmap("nonexistent")
	if err == nil {
		t.Error("expected error for missing bitmap")
	}
}
