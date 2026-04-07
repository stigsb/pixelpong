package main

// Device constants
const (
	DeviceJoy1      = 1
	DeviceJoy2      = 2
	DeviceKeyboard  = 3
)

// Event type constants
const (
	JoyAxisX   = 1
	JoyAxisY   = 2
	JoyButton1 = 3
)

// Axis/button value constants
const (
	ButtonDown    = 1
	ButtonNeutral = 0

	AxisUp      = -1
	AxisDown    = 1
	AxisLeft    = -1
	AxisRight   = 1
	AxisNeutral = 0
)

type InputEvent struct {
	Device    int
	EventType int
	Value     int
}
