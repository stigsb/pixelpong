export enum Device {
  JOY_1 = 1,
  JOY_2 = 2,
  KEYBOARD = 3,
}

export enum EventType {
  JOY_AXIS_X = 1,
  JOY_AXIS_Y = 2,
  JOY_BUTTON_1 = 3,
}

export enum EventValue {
  BUTTON_DOWN = 1,
  BUTTON_NEUTRAL = 0,
  AXIS_UP = -1,
  AXIS_DOWN = 1,
  AXIS_LEFT = -1,
  AXIS_RIGHT = 1,
  AXIS_NEUTRAL = 0,
}

export class Event {
  constructor(
    public readonly device: Device,
    public readonly eventType: EventType,
    public readonly value: EventValue,
  ) {}
}
