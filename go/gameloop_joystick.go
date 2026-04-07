package main

type JoystickTestLoop struct {
	BaseGameLoop
	p1Up   *Sprite
	p1Down *Sprite
	p2Up   *Sprite
	p2Down *Sprite
}

func NewJoystickTestLoop(fb *FrameBuffer, loader *BitmapLoader, font *Font) *JoystickTestLoop {
	j := &JoystickTestLoop{}
	j.init(fb, loader, font)

	j.p1Up, _ = loader.LoadSprite("joy_up", 6, 6)
	j.p1Down, _ = loader.LoadSprite("joy_down", 6, 17)
	j.p2Up, _ = loader.LoadSprite("joy_up", 35, 6)
	j.p2Down, _ = loader.LoadSprite("joy_down", 35, 17)

	j.AddSprite(j.p1Up)
	j.AddSprite(j.p1Down)
	j.AddSprite(j.p2Up)
	j.AddSprite(j.p2Down)

	return j
}

func (j *JoystickTestLoop) OnEnter() {
	j.BaseGameLoop.OnEnter()
	j.p1Up.Visible = false
	j.p1Down.Visible = false
	j.p2Up.Visible = false
	j.p2Down.Visible = false
}

func (j *JoystickTestLoop) OnEvent(event InputEvent) {
	if event.Device == DeviceJoy1 && event.EventType == JoyAxisY {
		switch event.Value {
		case AxisUp:
			j.p1Up.Visible = true
		case AxisDown:
			j.p1Down.Visible = true
		default:
			j.p1Up.Visible = false
			j.p1Down.Visible = false
		}
	} else if event.Device == DeviceJoy2 && event.EventType == JoyAxisY {
		switch event.Value {
		case AxisUp:
			j.p2Up.Visible = true
		case AxisDown:
			j.p2Down.Visible = true
		default:
			j.p2Up.Visible = false
			j.p2Down.Visible = false
		}
	}
}
