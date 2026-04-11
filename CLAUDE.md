# Pixelpong

Retro pong game with a C64-inspired 16-color palette, served over WebSocket. Originally built for HackRockheim. The same game logic and protocol are implemented across multiple languages.

## Architecture

All implementations follow the same module structure:

- **bitmap/** - Asset loading (sprites, fonts, backgrounds from `res/`)
- **frame/** - Framebuffer (47x27 pixels, double-buffered) and encoders (JSON with delta encoding)
- **gameloop/** - Game state machines (PressStart → MainGame, plus TestImage, JoystickTest, MakerFaire)
- **server/** - WebSocket connection handling, event dispatch, frame broadcast

Game loop interface in every language: `OnEnter()`, `OnFrameUpdate()`, `OnEvent(event)`, `NextLoop()`.

## Implementations

| Language   | Directory | Port | Build                          | Status   |
|------------|-----------|------|--------------------------------|----------|
| PHP        | `src/`    | 4432 | `composer install`             | Original |
| Go         | `go/`     | 4442 | `go build -o pixelpong .`      | Complete |
| TypeScript | `ts/`     | 4452 | `npm run build`                | Complete |
| Rust       | `rust/`   | 4462 | `cargo build --release`        | WIP      |
| Zig        | `zig/`    | 4472 | `zig build`                    | WIP      |
| C++        | `cpp/`    | 4482 | `meson setup builddir && ninja -C builddir` | WIP |

All accept `-p PORT` and `-f FPS` flags, and read `PONG_PORT`, `PONG_FPS`, `PONG_BIND_ADDR` env vars.

## Running

```bash
# PHP
php bin/server.php

# Go
cd go && go run .

# TypeScript
cd ts && npm start

# Rust
cd rust && cargo run

# Zig
cd zig && zig build run

# C++
cd cpp && ninja -C builddir && ./builddir/pixelpong
```

Then open `http://localhost:<port>/` in a browser.

## Testing

```bash
# PHP
vendor/bin/phpunit

# Go
cd go && go test ./...

# TypeScript
cd ts && npm test

# Rust
cd rust && cargo test

# Zig
cd zig && zig build test
```

## WebSocket Protocol

1. Client connects, sends `{"input": true, "output": true}`
2. Server replies with `{"frameInfo": {"width": 47, "height": 27, "palette": [...]}}`
3. Server streams `{"frame": {...}}` or `{"frameDelta": {...}}` (delta when <33% pixels changed)
4. Client sends `{"event": {"device": N, "eventType": N, "value": N}}` for input
5. Client sends `{"command": "restart"}` to restart

## Shared Resources

`res/` contains assets used by all implementations:
- `htdocs/index.html` - Browser client
- `sprites/*.txt` - ASCII sprite definitions (color indices)
- `bitmaps/47x27/*.txt` - Background bitmaps
- `fonts/5x7.json` - Font metadata and character map

## Conventions

- When porting to a new language, match the existing module structure and game behavior exactly
- Use language-idiomatic naming (PascalCase classes, camelCase methods, SCREAMING_SNAKE constants)
- Palette is C64-inspired, 16 colors indexed 0-15; transparent = -1
- Frame rate defaults to 10 FPS
- Ball speed: 3.0 px/frame base, +10% every 10 seconds
- Paddle speed: 10.0 px/frame
