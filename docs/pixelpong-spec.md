# PixelPong Game Specification

PixelPong is a WebSocket-based networked Pong game that renders a 47x27 pixel display to connected web clients. Originally built as a HackRockheim project.

## Display

- Resolution: 47 pixels wide, 27 pixels tall
- Pixel array: linear, indexed as `y * width + x`
- Default frame rate: 10 FPS (configurable)

## Color Palette

16 colors plus transparent, inspired by the Commodore 64 palette:

| ID | Name | Hex |
|----|------|-----|
| -1 | TRANSPARENT | (not rendered) |
| 0 | BLACK | #000000 |
| 1 | WHITE | #fcf9fc |
| 2 | RED | #933a4c |
| 3 | CYAN | #b6fafa |
| 4 | PURPLE | #d27ded |
| 5 | GREEN | #6acf6f |
| 6 | BLUE | #4f44d8 |
| 7 | YELLOW | #fbfb8b |
| 8 | ORANGE | #d89c5b |
| 9 | BROWN | #7f5307 |
| 10 | LIGHT_RED | #ef839f |
| 11 | DARK_GREY | #575753 |
| 12 | GREY | #a3a7a7 |
| 13 | LIGHT_GREEN | #b7fbbf |
| 14 | LIGHT_BLUE | #a397ff |
| 15 | LIGHT_GREY | #d0d0d0 |

## Rendering Pipeline

### FrameBuffer (Double-Buffered)

The framebuffer maintains two pixel arrays:

- **Background frame** (blank frame): the persistent backdrop, set by the current game loop. Copied into the current frame at each frame switch.
- **Current frame**: the working surface where pixels are drawn and sprites composited.

Each frame cycle:

1. The game loop updates game state and queues sprite draws via `drawBitmapAt()`.
2. `getAndSwitchFrame()` is called:
   - All queued bitmaps are composited onto the current frame.
   - The current frame is returned for encoding.
   - The current frame is reset to a copy of the background frame.
   - The bitmap queue is cleared.

### Sprite Compositing

When compositing a bitmap onto the frame:

- Iterate each pixel of the bitmap at the given (x, y) offset.
- Skip pixels that are TRANSPARENT (color -1). All other colors, including BLACK (0), are drawn.
- Clip to frame bounds: skip pixels where the target coordinate falls outside 0..width or 0..height.

### Sprites

A sprite wraps a bitmap with:

- Position (x, y) as integers
- Visibility flag (boolean)

Only visible sprites are rendered each frame. Sprites render in the order they were added (no z-ordering).

## Bitmap Assets

### Bitmap File Format (.txt)

Text files where each character maps to a color:

| Character | Color |
|-----------|-------|
| (space) | TRANSPARENT |
| `.` or `0` | BLACK |
| `#` or `1` | WHITE |
| `2` | RED |
| `3` | CYAN |
| `4` | PURPLE |
| `5` | GREEN |
| `6` | BLUE |
| `7` | YELLOW |
| `8` | ORANGE |
| `9` | BROWN |
| `a` | LIGHT_RED |
| `b` | DARK_GREY |
| `c` | GREY |
| `d` | LIGHT_GREEN |
| `e` | LIGHT_BLUE |
| `f` | LIGHT_GREY |

Lines may have a trailing `|` character which is stripped. Characters not in the map are ignored. Width is the length of the longest line; shorter lines are padded with TRANSPARENT.

### Bitmap Search Path

Bitmaps are loaded by name from a colon-separated list of directories. The first match for `{name}.txt` is used. Loaded bitmaps are cached by name.

Standard paths:
- `res/bitmaps/47x27` — full-screen game bitmaps (main_game, press_start, to_play, test_image, trondheim, maker, faire)
- `res/sprites` — small sprite bitmaps (ball, paddle, joy_up, joy_down, joy_left, joy_right, joy_button)

### Font System

Fonts are fixed-width monochrome glyph sets loaded from:
- A JSON metadata file (`{name}.json`) describing character dimensions, spacing, layout, and pixel color
- A PNG glyph sheet (`{name}.png`) containing all character glyphs

**JSON metadata format:**
```json
{
  "width": 5,
  "height": 7,
  "blankChar": " ",
  "pixelColor": "#333333",
  "charSpacing": [1, 2],
  "characterLines": [
    "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789.,    \"'?!@_*#$%&",
    "()+-/:;<=>[]^`{|}~\\"
  ]
}
```

- `width`, `height`: character cell dimensions in pixels
- `blankChar`: character used as a spacer in the layout (its glyph is also used as fallback for unknown characters)
- `pixelColor`: hex color of foreground pixels in the PNG
- `charSpacing`: `[horizontal, vertical]` pixel gap between characters in the PNG layout
- `characterLines`: each string is a row of characters in the PNG, laid out left-to-right with `charSpacing[0]` pixels between them. Rows are separated by `charSpacing[1]` pixels.

**Glyph extraction:** For each character in `characterLines`, read a `width × height` pixel region from the PNG. Pixels matching `pixelColor` are foreground (1); all others are background (0).

### TextBitmap

Renders a text string as a bitmap using a font:

- Total width: `(charWidth × numChars) + (spacing × (numChars - 1))`
- Total height: `charHeight`
- Each character's foreground pixels are colored with the specified color value. Background pixels remain 0 (BLACK).
- Default character spacing: 1 pixel

### ScrollingBitmap

A viewport window into a larger bitmap:

- Configured with viewport dimensions and scroll offset (x, y)
- Returns only the visible portion; areas outside the source bitmap are TRANSPARENT

## Input Events

### Device Types

| ID | Name |
|----|------|
| 1 | JOY_1 (Player 1 joystick) |
| 2 | JOY_2 (Player 2 joystick) |
| 3 | KEYBOARD |

### Event Types

| ID | Name |
|----|------|
| 1 | JOY_AXIS_X |
| 2 | JOY_AXIS_Y |
| 3 | JOY_BUTTON_1 |

### Event Values

| Value | Name | Usage |
|-------|------|-------|
| 1 | BUTTON_DOWN | Button pressed |
| 0 | BUTTON_NEUTRAL | Button released |
| -1 | AXIS_UP / AXIS_LEFT | Axis negative |
| 1 | AXIS_DOWN / AXIS_RIGHT | Axis positive |
| 0 | AXIS_NEUTRAL | Axis centered |

### Device-to-Paddle Mapping

- JOY_1 → LEFT paddle (index 0)
- JOY_2 → RIGHT paddle (index 0)

## Network Protocol

WebSocket connections on a configurable port (default: 4432).

### Server → Client

**Frame Info** (sent on connection):
```json
{
  "frameInfo": {
    "width": 47,
    "height": 27,
    "palette": {
      "0": "#000000",
      "1": "#fcf9fc",
      ...
    }
  }
}
```

**Full Frame** (sparse, only non-BLACK pixels):
```json
{
  "frame": {
    "0": 2,
    "47": 3,
    "94": 1
  }
}
```

**Delta Frame** (only changed pixels since last frame):
```json
{
  "frameDelta": {
    "100": 2,
    "101": 0
  }
}
```

Encoding strategy: if fewer than 1/3 of pixels changed, send a delta. Otherwise, send a full frame. If nothing changed, send nothing (null/no message).

### Client → Server

**Input Event:**
```json
{"event": {"device": 1, "eventType": 2, "value": -1}}
```

**Legacy Input Event:**
```json
{"V": -1, "D": 1, "T": 2}
```

**Control Messages:**
```json
{"input": true}
{"output": true}
{"command": "restart"}
```

- `input`: enable/disable this client's input events affecting the game
- `output`: enable/disable frame updates being sent to this client
- `command: "restart"`: restart the server

### Per-Client State

Each connected client has:
- A frame encoder instance (tracks previous frame for delta encoding)
- Input enabled flag (default: false)
- Output enabled flag (default: false)

## Game Loop State Machine

The server maintains one active game loop at a time. Game loops implement:

- `onEnter()` — called when the loop becomes active
- `onFrameUpdate()` — called once per frame
- `onEvent(event)` — called when an input event arrives

Game loops can trigger transitions to other loops.

### Screen Flow

```
TestImageScreen → PressStartToPlayGameLoop → MainGameLoop
                                                  ↺ (resets on game over + button press)
```

Transitions are triggered by JOY_BUTTON_1 release (BUTTON_NEUTRAL).

### TestImageScreen

- Displays the `test_image` bitmap as background
- On button press: transitions to PressStartToPlayGameLoop

### PressStartToPlayGameLoop

- Alternates between `press_start` and `to_play` background bitmaps
- Cycle: show `press_start` at elapsed time 0 (mod 4), switch to `to_play` at elapsed time 2 (mod 4)
- On button press: transitions to MainGameLoop

### JoystickTestGameLoop

- Debug screen showing joystick state via sprites
- Four sprites: P1 up (6, 6), P1 down (6, 17), P2 up (35, 6), P2 down (35, 17)
- All sprites start hidden
- JOY_AXIS_Y events toggle visibility: AXIS_UP shows up sprite, AXIS_DOWN shows down sprite, AXIS_NEUTRAL hides both

### TrondheimMakerFaireScreen

- Slideshow cycling through `trondheim`, `maker`, `faire` bitmaps
- Advances to the next frame every second
- No input handling

## Pong Game Logic (MainGameLoop)

### Game States

1. **INITIALIZING** — Measures frame time over 2 frames to calibrate physics
2. **WAITING** — Paddles and ball visible, waiting for a button press to start
3. **PLAYING** — Active gameplay with ball physics and paddle input
4. **GAMEOVER** — A player scored; waiting for button press to reset

### Constants

| Name | Value | Description |
|------|-------|-------------|
| BALL_SPEED | 3.0 | Base ball speed (unused in initial velocity calc) |
| PADDLE_SPEED | 10.0 | Paddle movement speed (pixels/second) |
| FRAME_EDGE_SIZE | 1.0 | Border thickness for ball/paddle bounds |
| BALL_SPEEDUP_EVERY_N_SECS | 10 | Seconds between ball speed increases |
| BALL_SPEEDUP_FACTOR | 1.10 | Multiplier applied to ball velocity on speedup |
| PADDLE_INFLUENCE | 0.5 | How much paddle movement affects ball angle |
| PADDLE_CENTER_Y | 12.0 | Vertical center for ball reset (PHP only; TS centers dynamically) |

### Initialization

On construction, the game loads:
- Background: `main_game` bitmap
- Two paddle sprites (from `paddle` bitmap)
- One ball sprite (from `ball` bitmap)

Derived values:
- `paddlePosX[LEFT]` = 1.0
- `paddlePosX[RIGHT]` = displayWidth - 1.0 - paddleWidth
- `ballPaddleLimitX[LEFT]` = paddlePosX[LEFT] + paddleWidth
- `ballPaddleLimitX[RIGHT]` = paddlePosX[RIGHT] - paddleWidth
- `ballEdgeLimitY[TOP]` = 1.0
- `ballEdgeLimitY[BOTTOM]` = displayHeight - ballHeight
- `paddleMinY` = FRAME_EDGE_SIZE (1.0)
- `paddleMaxY` = displayHeight - paddleHeight - FRAME_EDGE_SIZE

### Reset

When the game resets (on enter or after game over):
- Paddles center vertically: `displayHeight / 2.0 - paddleHeight / 2.0`
- Ball placed at: x = `ballPaddleLimitX[LEFT]`, y = `displayHeight / 2.0 - ballHeight / 2.0`
- Ball velocity zeroed
- State set to INITIALIZING

### Frame Rate Calibration (INITIALIZING)

- Frame 1: Record timestamp
- Frame 2: Compute `approxFrameTime = currentTime - initTimestamp`
- Transition to WAITING

### Starting the Game (WAITING → PLAYING)

On JOY_BUTTON_1 release:
- `ballDelta[X] = PADDLE_SPEED * approxFrameTime`
- `ballDelta[Y] = PADDLE_SPEED * approxFrameTime`
- Record start timestamp and speedup timestamp
- Transition to PLAYING

### Frame Update (PLAYING)

Each frame:

1. **Update paddle positions** — For each device (JOY_1, JOY_2):
   - `elapsed = currentTime - lastYAxisUpdateTime[paddle]`
   - `newPos = paddlePositions[paddle] + PADDLE_SPEED * elapsed * currentYAxis[paddle]`
   - Clamp to `[paddleMinY, paddleMaxY]`
   - Record `lastYAxisUpdateTime[paddle] = currentTime`

2. **Update paddle sprites** — Move paddle sprites to their integer positions

3. **Update ball position:**
   - `ballPos[X] += ballDelta[X]`
   - `ballPos[Y] += ballDelta[Y]`

4. **Check edge collisions:**
   - If `ballPos[Y] <= ballEdgeLimitY[TOP]` → bounce on top edge
   - If `ballPos[Y] >= ballEdgeLimitY[BOTTOM]` → bounce on bottom edge

5. **Check paddle collisions:**
   - If `ballPos[X] <= ballPaddleLimitX[LEFT]` → check left paddle hit
   - If `ballPos[X] >= ballPaddleLimitX[RIGHT]` → check right paddle hit

6. **Render sprites**

### Edge Bounce

```
bounceBack = edgeLimitY[edge] - ballPos[Y]
ballPos[Y] = edgeLimitY[edge] + bounceBack
ballDelta[Y] *= -1
```

The ball is reflected across the boundary to prevent it from getting stuck.

### Paddle Hit Detection

The ball hits a paddle if its Y position falls within the paddle's extended hitbox:

```
paddleYMin = paddlePositions[paddle] - ballHeight
paddleYMax = paddlePositions[paddle] + paddleHeight + ballHeight
hit = ballPos[Y] > paddleYMin AND ballPos[Y] < paddleYMax
```

The hitbox extends by `ballHeight` above and below the paddle to account for the ball's own size.

### Paddle Bounce

When the ball hits a paddle:

1. **Reflect X position:**
   ```
   bounceBack = ballPaddleLimitX[paddle] - ballPos[X]
   ballPos[X] = ballPaddleLimitX[paddle] + bounceBack
   ballDelta[X] *= -1
   ```

2. **Apply paddle influence** (only if paddle is moving, i.e., `currentYAxis[paddle] != 0`):
   ```
   influence = |ballDelta[X]| * PADDLE_INFLUENCE
   ballDelta[Y] += influence * paddleDirection
   ```

3. **Clamp Y velocity** to prevent extreme angles:
   ```
   maxY = |ballDelta[X]| * 1.5
   if |ballDelta[Y]| > maxY:
       ballDelta[Y] = sign(ballDelta[Y]) * maxY
   ```

4. **Check for ball speedup**

### Ball Speedup

On each paddle bounce, check if enough time has elapsed:

```
if (currentTime - lastSpeedupTimestamp >= BALL_SPEEDUP_EVERY_N_SECS):
    ballDelta[X] *= BALL_SPEEDUP_FACTOR
    ballDelta[Y] *= BALL_SPEEDUP_FACTOR
    lastSpeedupTimestamp = currentTime
```

### Scoring

If the ball passes a paddle's limit X without hitting the paddle:
- The opposing player wins
- State transitions to GAMEOVER
- `winningSide` is recorded

### Game Over → Reset

On JOY_BUTTON_1 release in GAMEOVER state:
- Full game reset (same as initial reset)

### Input Handling During Play

- JOY_AXIS_Y events:
  - If value is AXIS_NEUTRAL: update paddle position to current time (finalizes movement)
  - Set `currentYAxis[paddle]` to the event value (-1, 0, or 1)
- JOY_BUTTON_1 events are ignored during play

## Known Historical Bugs (PHP Original)

These bugs exist in the original PHP implementation and should be fixed in new ports:

1. **OffscreenFrameBuffer.getPixel() Y-bound check**: Checks `$this->width` instead of `$this->height` for the Y coordinate bounds.

2. **JoystickTestGameLoop.onEnter()**: Sets `p1UpSprite` visibility 4 times instead of setting all 4 different sprites (p1Up, p1Down, p2Up, p2Down).

3. **Sprite compositing skips BLACK**: The PHP `renderBitmaps()` only draws pixels where `pixel > 0`, which means BLACK (0) pixels in sprites are treated as transparent. This is likely unintentional — TRANSPARENT (-1) is the designated skip value. The TS implementation correctly checks `pixel !== TRANSPARENT`.

## Implementation Notes

### Differences Between PHP and TS Implementations

| Aspect | PHP | TypeScript |
|--------|-----|------------|
| Ball Y reset position | Hardcoded 12.0 | `displayHeight / 2.0 - ballHeight / 2.0` |
| Sprite transparency check | `pixel > 0` (skips BLACK) | `pixel !== TRANSPARENT` (correct) |
| Y velocity min clamp | `0.35 * \|ballDelta[X]\|` | Not applied |
| Default WebSocket port | 4432 | 4452 |
| Time source | `microtime(true)` | `performance.now() / 1000` |
| Frame encoder caching | Shared cache per encoder class | Per-client encoding |
| Game loop transitions | Via DI container | Via server.switchToGameLoop() |

The TS implementation is considered the corrected reference. New ports should follow the TS behavior for ball reset position and sprite transparency.
