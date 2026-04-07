# Pixelpong C++23 Port Design

## Overview

Port the PHP Pixelpong implementation to C++23 as a modernized rewrite. Same functionality, restructured to leverage C++ strengths: value types, compile-time computation, concepts, and `std::expected` for error handling.

## Build System

- **Meson** with subproject wraps for all dependencies
- Dependencies: uWebSockets (WebSocket server), nlohmann/json (JSON encoding), stb_image (PNG font loading)
- All fetched automatically via Meson wraps

## Project Structure

```
cpp/
  meson.build
  subprojects/
    uwebsockets.wrap
    nlohmann_json.wrap
    stb.wrap
  src/
    main.cpp
    bitmap/
      bitmap.hpp
      sprite.hpp
      font.hpp
      bitmap_loader.hpp
      font_loader.hpp
    frame/
      color.hpp
      frame_buffer.hpp
      frame_encoder.hpp
    game/
      event.hpp
      game_loop.hpp
      main_game.hpp
      press_start.hpp
      test_image.hpp
      joystick_test.hpp
      maker_faire.hpp
    server/
      game_server.hpp
      player_connection.hpp
```

Header-only where practical. Larger implementations (main_game, game_server) may use `.cpp` files.

## Core Types

### Color (`frame/color.hpp`)

`enum class Color : int8_t` with values Transparent (-1) through LightGrey (15). Commodore 64 style palette as a `constexpr std::array<std::string_view, 16>`.

### Bitmap (`bitmap/bitmap.hpp`)

Value type owning a flat `std::vector<Color>` with width and height. Copy/move semantics. Access via `pixel(x, y)`. No inheritance hierarchy.

Free function `make_text_bitmap(font, text, color, spacing) -> Bitmap` replaces PHP's TextBitmap subclass.

### ScrollingBitmap (`bitmap/bitmap.hpp`)

Holds a `const Bitmap&` plus x/y offset. Provides viewport clipping over a larger bitmap. Not a Bitmap subtype -- returns a new `std::vector<Color>` via `get_pixels()`.

### Sprite (`bitmap/sprite.hpp`)

Simple struct: `const Bitmap*` (non-owning), position (int x/y), visibility flag.

### Font (`bitmap/font.hpp`)

Value type owning `std::unordered_map<char, std::vector<Color>>` of character bitmaps plus width/height and blank character fallback.

### FontLoader (`bitmap/font_loader.hpp`)

Loads fonts from PNG + JSON metadata files using stb_image and nlohmann/json. Returns `std::expected<Font, std::string>`.

### BitmapLoader (`bitmap/bitmap_loader.hpp`)

Loads bitmaps from `.txt` files with character-to-color mapping. Supports multiple search paths (colon-separated). Caches loaded bitmaps. Returns `std::expected<Bitmap, std::string>`.

Character mapping: space=Transparent, '.'=Black, '#'=White, '0'-'9' and 'a'-'f' map to the 16 colors.

### Event (`game/event.hpp`)

Plain struct with nested enum classes for Device (Joy1, Joy2, Keyboard), Type (AxisX, AxisY, Button1), and an int8_t value (-1, 0, 1).

## Frame Buffer & Encoders

### FrameBuffer (`frame/frame_buffer.hpp`)

Single concrete class, double-buffered. Owns two flat `std::vector<Color>` buffers plus a background frame. Sprites are queued via `draw_bitmap()` and composited during `swap()`, which returns `std::span<const Color>`.

Default dimensions: 47x27 (configurable).

### JsonEncoder (`frame/frame_encoder.hpp`)

Stateful -- tracks previous frame for delta encoding. `encode(std::span<const Color>) -> std::optional<std::string>`. Returns nullopt if no changes. Sends delta if < 1/3 pixels changed, full frame otherwise. Separate `encode_frame_info()` method for dimension/palette metadata.

### AsciiEncoder (`frame/frame_encoder.hpp`)

Stateless debug encoder. Maps colors to characters.

## Game Loops

### GameLoop (`game/game_loop.hpp`)

Abstract base class with virtual dispatch:
- `on_enter()` -- called when loop becomes active
- `on_frame_update()` -- called each frame
- `on_event(const Event&)` -- called on input

### GameContext

Struct holding references to FrameBuffer, BitmapLoader, FontLoader. Passed to game loop constructors, replacing the PHP DI container.

### SwitchLoopFn

`std::function<void(std::unique_ptr<GameLoop>)>` callback stored in each game loop for transitioning between screens.

### Concrete Game Loops

- **TestImage** -- displays test image bitmap, transitions to PressStart on button press
- **PressStart** -- alternates "PRESS START" / "TO PLAY" frames every 2 seconds, transitions to MainGame on button
- **MainGame** -- full Pong implementation with ball physics, paddle control, collision detection, speed ramping. Game state as `std::variant<Initializing, Waiting, Playing, GameOver>`
- **JoystickTest** -- shows/hides directional sprites based on joystick input
- **MakerFaire** -- cycles through 3 animation frames (Trondheim Maker Faire branding)

### MainGame Physics

- Ball speed: 3.0, paddle speed: 10.0, paddle influence: 0.5
- Ball speeds up by 10% every 10 seconds
- Double positions with integer sprite rendering
- Paddle/ball collision with bounce angle influenced by paddle hit position

## Server

### GameServer (`server/game_server.hpp`)

Owns the uWebSockets app, FrameBuffer, current GameLoop, and all player connections. Periodic timer at configured FPS drives the game loop. On each tick: updates game, swaps frame buffer, encodes frame, broadcasts to connected clients.

Handles WebSocket JSON messages: input enable/disable, output enable/disable, events (joystick/keyboard), and commands (restart).

### PlayerConnection (`server/player_connection.hpp`)

Simple struct: owns a JsonEncoder, has input/output enabled flags. Stored in `std::unordered_map` keyed by WebSocket pointer.

### main.cpp

Parses CLI args (--port, --fps, --bind-addr with same defaults as PHP: 4432, 10.0, 0.0.0.0). Constructs GameContext, creates GameServer, runs event loop.

## Resource Files

The C++ port reuses the existing `res/` directory (bitmaps, sprites, fonts) unchanged. Resource path is resolved relative to the executable or via a CLI argument.

## Error Handling

- File loading operations return `std::expected<T, std::string>`
- Runtime errors (missing resources at startup) terminate with a clear message
- Network errors are logged and the connection is dropped
