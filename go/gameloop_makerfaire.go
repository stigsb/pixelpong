package main

import "time"

type MakerFaireLoop struct {
	fb           *FrameBuffer
	frames       [][]int
	previousTime time.Time
	currentIndex int
}

func NewMakerFaireLoop(fb *FrameBuffer, loader *BitmapLoader) *MakerFaireLoop {
	m := &MakerFaireLoop{fb: fb}
	for _, name := range []string{"trondheim", "maker", "faire"} {
		bm, err := loader.LoadBitmap(name)
		if err != nil {
			panic("failed to load " + name + ": " + err.Error())
		}
		m.frames = append(m.frames, bm.Pixels())
	}
	return m
}

func (m *MakerFaireLoop) OnEnter() {
	m.currentIndex = 0
	m.fb.SetBackgroundFrame(m.frames[0])
	m.previousTime = time.Now()
}

func (m *MakerFaireLoop) OnFrameUpdate() {
	now := time.Now()
	if now.Unix() > m.previousTime.Unix() {
		m.currentIndex = (m.currentIndex + 1) % len(m.frames)
		m.fb.SetBackgroundFrame(m.frames[m.currentIndex])
	}
	m.previousTime = now
}

func (m *MakerFaireLoop) OnEvent(event InputEvent) {
	// No input handling
}

func (m *MakerFaireLoop) NextLoop() string {
	return ""
}
