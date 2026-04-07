package main

import "time"

type PressStartToPlayLoop struct {
	BaseGameLoop
	pressStartFrame []int
	toPlayFrame     []int
	enterTime       time.Time
	previousElapsed int
}

func NewPressStartToPlayLoop(fb *FrameBuffer, loader *BitmapLoader, font *Font) *PressStartToPlayLoop {
	p := &PressStartToPlayLoop{}
	p.init(fb, loader, font)
	bm1, err := loader.LoadBitmap("press_start")
	if err != nil {
		panic("failed to load press_start: " + err.Error())
	}
	p.pressStartFrame = bm1.Pixels()
	bm2, err := loader.LoadBitmap("to_play")
	if err != nil {
		panic("failed to load to_play: " + err.Error())
	}
	p.toPlayFrame = bm2.Pixels()
	return p
}

func (p *PressStartToPlayLoop) OnEnter() {
	p.fb.SetBackgroundFrame(p.pressStartFrame)
	p.enterTime = time.Now()
	p.previousElapsed = 0
}

func (p *PressStartToPlayLoop) OnFrameUpdate() {
	elapsed := int(time.Since(p.enterTime).Seconds())
	if elapsed > p.previousElapsed {
		switch elapsed % 4 {
		case 0:
			p.fb.SetBackgroundFrame(p.pressStartFrame)
		case 2:
			p.fb.SetBackgroundFrame(p.toPlayFrame)
		}
	}
	p.previousElapsed = elapsed
}

func (p *PressStartToPlayLoop) OnEvent(event InputEvent) {
	if event.EventType == JoyButton1 && event.Value == ButtonNeutral {
		p.RequestSwitch("maingame")
	}
}
