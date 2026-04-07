package main

type TestImageScreen struct {
	BaseGameLoop
}

func NewTestImageScreen(fb *FrameBuffer, loader *BitmapLoader, font *Font) *TestImageScreen {
	t := &TestImageScreen{}
	t.init(fb, loader, font)
	bm, err := loader.LoadBitmap("test_image")
	if err != nil {
		panic("failed to load test_image: " + err.Error())
	}
	t.background = bm
	return t
}

func (t *TestImageScreen) OnEvent(event InputEvent) {
	if event.EventType == JoyButton1 && event.Value == ButtonNeutral {
		t.RequestSwitch("pressstart")
	}
}
