# Pixelpong

A retro pong game with a C64-inspired 16-color palette, served over WebSocket. Originally built for HackRockheim.

The game renders to a 47x27 pixel framebuffer and streams frames as JSON to browser clients. Each implementation is a standalone WebSocket server with the same protocol and game logic.

## Playing

Open the HTML client (e.g. `res/htdocs/index.html` or Go's built-in server) in a browser. Controls:

- **Player 1:** A (up), Z (down), Space (start/restart)
- **Player 2:** K (up), M (down)

## Port Assignments

Each language uses a different default port so all implementations can run side by side:

| Language   | Directory | Default Port |
|------------|-----------|--------------|
| PHP        | `src/`    | 4432         |
| Go         | `go/`     | 4442         |
| TypeScript | `ts/`     | 4452         |
| Rust       | (planned) | 4462         |
| Zig        | `zig/`    | 4472         |
| C++        | (planned) | 4482         |

All implementations accept `-p PORT` to override, and read `PONG_PORT`, `PONG_FPS`, and `PONG_BIND_ADDR` environment variables.

---

## PHP

The original implementation using Ratchet WebSockets and ReactPHP.

### Prerequisites

- PHP 5.6+
- Composer

### Build & Run

```sh
composer install
bin/server.php
```

Options: `-p PORT`, `-f FPS`

### Tests

```sh
vendor/bin/phpunit
```

---

## Go

Standalone binary with embedded static files. Serves both the HTML client and WebSocket on the same port.

### Prerequisites

- Go 1.21+

### Build & Run

```sh
cd go
go build -o pixelpong .
./pixelpong
```

Options: `-p PORT`, `-f FPS`

Open `http://localhost:4442/` in a browser to play.

### Tests

```sh
cd go
go test ./...
```

---

## TypeScript

Node.js implementation using the `ws` WebSocket library.

### Prerequisites

- Node.js 18+

### Build & Run

```sh
cd ts
npm install
npm run build
npm start
```

For development with auto-reload:

```sh
npm run dev
```

Options: `-p PORT`, `-f FPS`

### Tests

```sh
cd ts
npm test
```

---

## Zig

Minimal implementation with a hand-rolled WebSocket server. No external dependencies.

### Prerequisites

- Zig 0.15+

### Build & Run

```sh
cd zig
zig build
# Run from the project root so it finds res/
cd ..
zig/zig-out/bin/pixelpong
```

Options: `-p PORT`, `-f FPS`, `-b BIND_ADDR`

### Tests

```sh
cd zig
zig build test
```
