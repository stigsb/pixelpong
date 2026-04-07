package main

// GameLoop is the interface for game state machines.
type GameLoop interface {
	// OnEnter is called when the server switches to this game loop.
	OnEnter()
	// OnFrameUpdate is called each tick to update game state and draw.
	OnFrameUpdate()
	// OnEvent processes a single input event.
	OnEvent(event InputEvent)
}

// SwitchRequester can signal that the game loop wants to transition.
type SwitchRequester interface {
	// NextLoop returns the name of the game loop to switch to, or "" to stay.
	NextLoop() string
}

// BaseGameLoop provides common game loop functionality.
type BaseGameLoop struct {
	fb         *FrameBuffer
	loader     *BitmapLoader
	fontLoader *Font
	background *SimpleBitmap
	sprites    []*Sprite
	nextLoop   string
}

func (b *BaseGameLoop) init(fb *FrameBuffer, loader *BitmapLoader, font *Font) {
	b.fb = fb
	b.loader = loader
	b.fontLoader = font
	b.sprites = nil
	b.nextLoop = ""
}

func (b *BaseGameLoop) OnEnter() {
	if b.background != nil {
		b.fb.SetBackgroundFrame(b.background.Pixels())
	}
}

func (b *BaseGameLoop) OnFrameUpdate() {
	b.renderVisibleSprites()
}

func (b *BaseGameLoop) AddSprite(s *Sprite) {
	b.sprites = append(b.sprites, s)
}

func (b *BaseGameLoop) renderVisibleSprites() {
	for _, s := range b.sprites {
		if s.Visible {
			b.fb.DrawBitmapAt(s.Bitmap(), s.X, s.Y)
		}
	}
}

func (b *BaseGameLoop) NextLoop() string {
	nl := b.nextLoop
	b.nextLoop = ""
	return nl
}

func (b *BaseGameLoop) RequestSwitch(name string) {
	b.nextLoop = name
}
