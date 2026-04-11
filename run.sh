#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

usage() {
    echo "Usage: $0 <language>"
    echo ""
    echo "Languages: php, go, ts, rust, zig, cpp"
    exit 1
}

[[ $# -lt 1 ]] && usage

lang="$1"
shift

case "$lang" in
    php)
        port=4432
        cd "$SCRIPT_DIR"
        composer install --quiet 2>/dev/null || true
        echo "Starting PHP server on port $port..."
        php bin/server.php -p "$port" "$@" &
        ;;
    go)
        port=4442
        cd "$SCRIPT_DIR/go"
        echo "Building Go..."
        go build -o pixelpong .
        echo "Starting Go server on port $port..."
        ./pixelpong -p "$port" "$@" &
        ;;
    ts|typescript)
        port=4452
        cd "$SCRIPT_DIR/ts"
        echo "Building TypeScript..."
        npm run build --silent
        echo "Starting TypeScript server on port $port..."
        node dist/server.js -p "$port" "$@" &
        ;;
    rust|rs)
        port=4462
        cd "$SCRIPT_DIR/rust"
        echo "Building Rust..."
        cargo build --release --quiet
        echo "Starting Rust server on port $port..."
        ./target/release/pixelpong -p "$port" "$@" &
        ;;
    zig)
        port=4472
        cd "$SCRIPT_DIR/zig"
        echo "Building Zig..."
        zig build
        echo "Starting Zig server on port $port..."
        zig build run -- -p "$port" "$@" &
        ;;
    cpp|c++)
        port=4482
        cd "$SCRIPT_DIR/cpp"
        if [[ ! -d builddir ]]; then
            echo "Setting up Meson..."
            meson setup builddir
        fi
        echo "Building C++..."
        ninja -C builddir
        echo "Starting C++ server on port $port..."
        ./builddir/pixelpong -p "$port" "$@" &
        ;;
    *)
        echo "Unknown language: $lang"
        usage
        ;;
esac

SERVER_PID=$!
sleep 1

echo "Opening http://localhost:$port/"
open "http://localhost:$port/"

echo "Server running (PID $SERVER_PID). Press Ctrl+C to stop."
trap "kill $SERVER_PID 2>/dev/null" EXIT
wait $SERVER_PID
