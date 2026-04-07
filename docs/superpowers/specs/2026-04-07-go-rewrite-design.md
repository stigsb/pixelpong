# Pixelpong Go Rewrite Design

## Overview

Rewrite the Pixelpong game server from PHP/Ratchet to Go, producing a single
self-contained binary with embedded resources. The existing HTML/JS client is
kept unchanged. The wire protocol is preserved exactly so the Go server is a
drop-in replacement.

## Goals

- Faithful port of all game modes and features to idiomatic Go
- Single static binary with all resources embedded via `//go:embed`
- Identical WebSocket protocol so the existing `index.html` client works unmodified
- Simpler deployment: multi-stage Docker build producing a scratch-based image
- Clean, idiomatic Go code: explicit config, goroutine-based concurrency, no DI framework

## Non-Goals

- Client rewrite (kept as-is)
- New features or game modes
- Protocol changes

## Architecture

### Package Structure

All Go source lives in a single `main` package at the repository root. The app
is small enough that sub-packages would add ceremony without value.

```
pixelpong/
├── main.go                    # Entry point, HTTP server, WebSocket upgrade, game ticker
├── config.go                  # Config struct, flag/env parsing
├── server.go                  # Connection manager, broadcast, message handling
├── event.go                   # InputEvent type, device/event-type constants
├── framebuffer.go             # FrameBuffer: double-buffered pixel grid
├── encoder.go                 # JSON frame encoder with delta support
├── palette.go                 # 16-color C64 palette constants
├── bitmap.go                  # Bitmap interface, SimpleBitmap, Sprite, ScrollingBitmap
├── textbitmap.go              # TextBitmap: renders strings using a pixel font
├── font.go                    # Font loader (from embedded 5x7.json)
├── loader.go                  # Bitmap loader (from embedded text files)
├── gameloop.go                # GameLoop interface definition
├── gameloop_testimage.go      # TestImageScreen
├── gameloop_pressstart.go     # PressStartScreen (blinking "press start")
├── gameloop_main.go           # MainGameLoop (pong gameplay + game-over)
├── gameloop_joystick.go       # JoystickTestLoop
├── gameloop_makerfaire.go     # MakerFaireLoop (rotating display)
├── res/                       # Embedded resources (unchanged from PHP version)
│   ├── bitmaps/47x27/         # Screen background bitmaps
│   ├── bitmaps/30x24/         # Alternative resolution bitmaps
│   ├── sprites/               # Paddle, ball, joystick indicator sprites
│   ├── fonts/5x7.json         # Pixel font definition
│   ├── fonts/5x7.png          # Font reference image (not used at runtime)
│   └── htdocs/index.html      # Client (served as static file)
├── go.mod
├── go.sum
└── Dockerfile
```

### Concurrency Model

```
main goroutine
  ├── HTTP server (net/http.ListenAndServe)
  │     └── per-connection read goroutine (reads WebSocket input)
  └── game ticker goroutine (time.Ticker at 1/FPS)
        ├── calls gameLoop.Update(dt, fb)
        ├── encodes frame
        └── broadcasts to all connections (under read lock)
```

- **One ticker goroutine** drives the game loop and broadcasts frames.
- **One read goroutine per connection** reads input messages and sends events
  to the game loop via a channel.
- A `sync.RWMutex` protects the connection map. The ticker takes a read lock
  to broadcast; connect/disconnect take a write lock.
- Input events are funneled through a buffered channel to the ticker goroutine,
  so the game loop processes events single-threaded with no locking needed.

### Connection Manager

```go
type Server struct {
    mu          sync.RWMutex
    connections map[*websocket.Conn]*PlayerConnection
    events      chan InputEvent
    gameLoop    GameLoop
    fb          *FrameBuffer
    config      Config
}

type PlayerConnection struct {
    conn    *websocket.Conn
    encoder *JsonFrameEncoder
    input   bool
    output  bool
}
```

### Game Loop Interface

```go
type GameLoop interface {
    // OnEvent processes a single input event.
    OnEvent(event InputEvent)

    // Update advances game state by dt seconds and draws to the frame buffer.
    Update(dt float64, fb *FrameBuffer)

    // NextLoop returns the game loop to transition to, or nil to stay on this one.
    NextLoop() GameLoop
}
```

The server checks `NextLoop()` after each `Update()`. When non-nil, it replaces
the current game loop. This handles the flow:
TestImageScreen -> PressStartScreen -> MainGameLoop -> (game over) -> PressStartScreen.

### Game Loops

**TestImageScreen**: Draws a static test image from `res/bitmaps/47x27/test_image.txt`.
Transitions to PressStartScreen after receiving any button press.

**PressStartScreen**: Loads the press-start bitmap. Blinks "PRESS START" text.
Transitions to MainGameLoop on button press.

**MainGameLoop**: States: INITIALIZING, WAITING, PLAYING, GAMEOVER.
- Ball moves at 3.0 px/sec base speed, increases 10% every 10 seconds.
- Paddle speed 10.0 px/sec.
- Collision detection: ball reflects off paddles and walls.
- Win condition: ball passes paddle boundary.
- On game over, shows winner text, transitions to PressStartScreen on button press.

**JoystickTestLoop**: Displays joystick state indicators for debugging input.

**MakerFaireLoop**: Rotating display of screens for exhibition use.

### Frame Buffer

```go
type FrameBuffer struct {
    width, height int
    current       []int  // current frame being drawn to
    background    []int  // background layer (cleared to this each frame)
    sprites       []*Sprite
}
```

- `drawBitmapAt(bm Bitmap, x, y int)` blits a bitmap onto the current frame.
- `renderBitmaps()` clears current to background, then draws all visible sprites.
- `getAndSwitchFrame()` returns the current frame and resets it.

Pixel values are color indices 0-15, with -1 for transparent.

### Frame Encoding

```go
type JsonFrameEncoder struct {
    prevFrame []int
    width     int
    height    int
}
```

`encodeFrame(frame []int)` returns one of:
- `nil` if no pixels changed
- `{"frameDelta": {...}}` if < 1/3 of pixels changed (sparse: only changed pixel indices)
- `{"frame": {...}}` for full frames (sparse: only non-background pixels)

The `frameInfo` message is sent once on connection:
```json
{"frameInfo": {"width": 47, "height": 27, "palette": ["#000000", ...]}}
```

### Input Events

```go
type InputEvent struct {
    Device    int // JOY_1=1, JOY_2=2, KEYBOARD=3
    EventType int // AXIS_X=1, AXIS_Y=2, BUTTON_1=3
    Value     int // -1, 0, 1
    Conn      *websocket.Conn // source connection (for restart command routing)
}
```

Client messages parsed:
- `{"event": {"device": N, "eventType": N, "value": N}}` -> InputEvent
- `{"input": true, "output": true}` -> toggle flags on PlayerConnection
- `{"command": "restart"}` -> reset to TestImageScreen

### Bitmap System

```go
type Bitmap interface {
    Width() int
    Height() int
    PixelAt(x, y int) int
}
```

Implementations:
- **SimpleBitmap**: flat pixel array, `Width()*Height()` entries
- **Sprite**: wraps a Bitmap with position (X, Y) and Visible flag
- **ScrollingBitmap**: viewport into a larger bitmap with scroll offset
- **TextBitmap**: renders a string using the embedded pixel font

### Resource Loading

```go
//go:embed res
var resources embed.FS
```

- `loadBitmap(path string) *SimpleBitmap` reads a text file from embedded FS,
  maps characters to color indices using the existing format.
- `loadFont(path string) *Font` reads the 5x7.json font definition.
- `index.html` served via `http.FileServer(http.FS(...))`.

### Color Palette

16 C64-inspired colors, same as PHP version:

| Index | Color       | Hex     |
|-------|-------------|---------|
| 0     | Black       | #000000 |
| 1     | White       | #fcf9fc |
| 2     | Red         | #cf3640 |
| 3     | Cyan        | #60d5e6 |
| 4     | Purple      | #cf41ca |
| 5     | Green       | #52b748 |
| 6     | Blue        | #3a30ab |
| 7     | Yellow      | #eae47a |
| 8     | Orange      | #cf7e38 |
| 9     | Brown       | #7e5d30 |
| 10    | Light Red   | #ef8080 |
| 11    | Dark Grey   | #5a5a5a |
| 12    | Grey        | #8a8a8a |
| 13    | Light Green | #97ef80 |
| 14    | Light Blue  | #817eef |
| 15    | Light Grey  | #bfbfbf |

### Configuration

```go
type Config struct {
    Port     int     // -p flag, PONG_PORT env, default 4432
    BindAddr string  // PONG_BIND_ADDR env, default "0.0.0.0"
    FPS      float64 // -f flag, PONG_FPS env, default 10.0
    Width    int     // default 47
    Height   int     // default 27
}
```

Flag parsing with `flag` package. Environment variables as fallback when flags
are at their default values.

### HTTP Server

```go
// Single handler on "/" that checks for WebSocket upgrade
http.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
    if websocket.AcceptOptions can upgrade {
        server.HandleWebSocket(w, r)
        return
    }
    // Fall through to static file serving
    fileServer.ServeHTTP(w, r)
})
```

The PHP version upgrades on `/` for WebSocket connections. The Go server does
the same: a single handler on `/` checks for the `Upgrade: websocket` header.
If present, it upgrades to WebSocket. Otherwise, it serves static files from
the embedded `res/htdocs/` directory.

### Docker

```dockerfile
FROM golang:1.24 AS builder
WORKDIR /app
COPY go.mod go.sum ./
RUN go mod download
COPY . .
RUN CGO_ENABLED=0 GOOS=linux go build -o pixelpong .

FROM scratch
COPY --from=builder /app/pixelpong /pixelpong
EXPOSE 4432
ENTRYPOINT ["/pixelpong"]
```

### Testing

Port the existing PHPUnit tests:
- Frame encoder tests (full frame, delta, no-change)
- Frame buffer tests (draw, clear, sprite rendering)

Use Go's `testing` package. No test framework dependencies.

### Dependencies

- `nhooyr.io/websocket` — WebSocket server
- Standard library for everything else

### Migration Path

1. Build the Go version alongside the PHP version
2. Run both against the same `index.html` client to verify compatibility
3. Once verified, the Go binary replaces the PHP server entirely
