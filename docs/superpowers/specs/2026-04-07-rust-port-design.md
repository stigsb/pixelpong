# PixelPong Rust Port — Design Spec

## Overview

Port the PHP PixelPong WebSocket Pong game to Rust in the `rust/` subdirectory. Faithful port of all functionality with idiomatic Rust patterns. Reuses the existing `res/` asset directory.

## Decisions

- **WebSocket:** `tokio-tungstenite` — lightweight, direct control over event loop
- **Font loading:** `png` crate to load existing `5x7.json` + `5x7.png` font assets
- **Game loops:** All 5 ported (TestImage, PressStart, MainGame, JoystickTest, MakerFaire)
- **Bug fixes:** Fix Y-bound check in OffscreenFrameBuffer and JoystickTest sprite visibility

## Architecture

Same layered architecture as PHP, mapped to idiomatic Rust:

```
WebSocket Clients
       ↓
GameServer (tokio-tungstenite, async)
       ↓
GameLoop trait (state machine, dyn dispatch)
       ↓
FrameBuffer (double-buffered pixel array)
       ↓
FrameEncoder trait (JSON delta / ASCII)
       ↓
PlayerConnection (per-client state)
```

## Module Structure

```
rust/
├── Cargo.toml
└── src/
    ├── main.rs              # CLI args, tokio runtime, server startup
    ├── bitmap/
    │   ├── mod.rs           # Bitmap trait, SimpleBitmap
    │   ├── sprite.rs        # Sprite (position + visibility + bitmap)
    │   ├── text_bitmap.rs   # TextBitmap (renders text with Font)
    │   ├── scrolling.rs     # ScrollingBitmap (viewport into larger bitmap)
    │   ├── font.rs          # Font + FontLoader (JSON + PNG)
    │   └── loader.rs        # BitmapLoader (txt files, path search, cache)
    ├── frame/
    │   ├── mod.rs           # FrameBuffer trait
    │   ├── offscreen.rs     # OffscreenFrameBuffer (double-buffered)
    │   ├── json_encoder.rs  # JsonFrameEncoder (sparse + delta)
    │   └── ascii_encoder.rs # AsciiFrameEncoder (terminal debug)
    ├── server/
    │   ├── mod.rs           # Event, Color
    │   ├── game_server.rs   # GameServer (WebSocket + frame loop)
    │   └── player.rs        # PlayerConnection
    └── gameloop/
        ├── mod.rs           # GameLoop trait + BaseGameLoop
        ├── main_game.rs     # MainGameLoop (Pong)
        ├── press_start.rs   # PressStartToPlayGameLoop
        ├── joystick_test.rs # JoystickTestGameLoop
        ├── test_image.rs    # TestImageScreen
        └── maker_faire.rs   # TrondheimMakerFaireScreen
```

## Key Design Decisions

### Ownership Model

`GameServer` owns the `FrameBuffer` and current `GameLoop`. Game loops receive `&mut FrameBuffer` on each frame update rather than holding a shared reference. This avoids `Rc<RefCell<>>` complexity.

### GameLoop Trait

```rust
enum GameLoopTransition {
    SwitchTo(Box<dyn GameLoop>),
}

trait GameLoop {
    fn on_enter(&mut self, fb: &mut dyn FrameBuffer, loader: &mut BitmapLoader);
    fn on_frame_update(&mut self, fb: &mut dyn FrameBuffer);
    fn on_event(&mut self, event: Event) -> Option<GameLoopTransition>;
}
```

Game loops return `Option<GameLoopTransition>` to signal state changes instead of reaching back into the server via a DI container. The server checks the return value and performs the switch.

### Async Model

Tokio runtime with `tokio::time::interval` for the frame tick. Each WebSocket client connection is a spawned task. Communication between the frame loop and client tasks uses `tokio::sync::broadcast` for frame data and `tokio::sync::mpsc` for client-to-server events.

```
Client task ──mpsc──> GameServer frame loop
GameServer frame loop ──broadcast──> Client tasks
```

### FrameBuffer

```rust
trait FrameBuffer {
    fn width(&self) -> usize;
    fn height(&self) -> usize;
    fn get_pixel(&self, x: usize, y: usize) -> i8;
    fn set_pixel(&mut self, x: usize, y: usize, color: i8);
    fn get_frame(&self) -> &[i8];
    fn get_and_switch_frame(&mut self) -> Vec<i8>;
    fn set_background_frame(&mut self, frame: Vec<i8>);
    fn draw_bitmap_at(&mut self, bitmap: &dyn Bitmap, x: i32, y: i32);
}
```

Pixel values are `i8` to accommodate `TRANSPARENT = -1`. Frame arrays are `Vec<i8>` with index = `y * width + x`.

### Frame Encoding

```rust
trait FrameEncoder {
    fn encode_frame(&mut self, frame: &[i8]) -> Option<String>;
    fn encode_frame_info(&self, width: usize, height: usize) -> String;
}
```

Returns `None` when frame is identical to previous (no data to send). `JsonFrameEncoder` tracks previous frame for delta encoding. Sends full frame when >1/3 pixels changed, delta otherwise.

### Bitmap System

```rust
trait Bitmap {
    fn width(&self) -> usize;
    fn height(&self) -> usize;
    fn pixels(&self) -> &[i8];
}
```

- `SimpleBitmap` — static pixel data
- `TextBitmap` — renders text string using a Font, produces pixel array on construction
- `ScrollingBitmap` — viewport window into a larger bitmap
- `Sprite` — wraps a `Box<dyn Bitmap>` with position (x, y) and visibility flag
- `Font` — character glyph storage, loaded from JSON + PNG
- `BitmapLoader` — loads `.txt` bitmap files from search path, caches results

### Color Palette

16 colors (C64-inspired) plus transparent:

| ID | Name | Hex |
|----|------|-----|
| -1 | TRANSPARENT | — |
| 0 | BLACK | #000000 |
| 1 | WHITE | #fcf9fc |
| 2 | RED | #cf3540 |
| 3 | CYAN | #68d5c8 |
| 4 | PURPLE | #cf40c7 |
| 5 | GREEN | #44c740 |
| 6 | BLUE | #3526d9 |
| 7 | YELLOW | #f0ee4c |
| 8 | ORANGE | #cf6f27 |
| 9 | BROWN | #874b13 |
| 10 | LIGHT_RED | #fb6e6b |
| 11 | DARK_GREY | #52524c |
| 12 | GREY | #8b8b83 |
| 13 | LIGHT_GREEN | #a4fa44 |
| 14 | LIGHT_BLUE | #7069f5 |
| 15 | LIGHT_GREY | #b3b3ab |

### Network Protocol

Identical to the PHP version:

**Client → Server (JSON):**
- `{"event": {"device": N, "eventType": N, "value": N}}` — input event
- `{"V": N, "D": N, "T": N}` — legacy format
- `{"input": bool}` — toggle input processing
- `{"output": bool}` — toggle frame output
- `{"command": "restart"}` — restart server

**Server → Client (JSON):**
- `{"frameInfo": {"width": 47, "height": 27, "palette": {...}}}` — on connect
- `{"frame": {idx: color, ...}}` — full frame (sparse, non-zero pixels only)
- `{"frameDelta": {idx: color, ...}}` — delta frame (changed pixels only)

### Game Constants

From `MainGameLoop`:
- Display: 47×27 pixels
- Ball speed: 3.0 px/sec (speeds up 10% every 10 seconds)
- Paddle speed: 10.0 px/sec
- Paddle influence on ball: 0.5
- Frame edge: 1.0 px
- Default FPS: 10
- Default port: 4432

### Dependencies

```toml
[dependencies]
tokio = { version = "1", features = ["full"] }
tokio-tungstenite = "0.24"
serde = { version = "1", features = ["derive"] }
serde_json = "1"
png = "0.17"
clap = { version = "4", features = ["derive"] }
futures-util = "0.3"

[dev-dependencies]
```

### Bug Fixes from PHP

1. **OffscreenFrameBuffer::get_pixel() Y-bound check** — PHP checks `$this->width` for Y; Rust checks `self.height`
2. **JoystickTestGameLoop::on_enter()** — PHP sets p1_up visibility 4 times; Rust sets all 4 sprites correctly

### Assets

Reuses existing `res/` directory at `../res` relative to the Rust binary (or configurable). Same bitmap `.txt` files, font JSON + PNG files.
