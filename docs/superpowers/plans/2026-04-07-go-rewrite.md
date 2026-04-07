# Pixelpong Go Rewrite Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rewrite the pixelpong game server from PHP to Go, producing a single binary with embedded resources that is a drop-in replacement for the PHP server.

**Architecture:** Single `main` package Go application. WebSocket server using nhooyr.io/websocket. Game loop driven by a time.Ticker. Resources embedded with `//go:embed`. Identical wire protocol so the existing HTML client works unmodified.

**Tech Stack:** Go 1.24, nhooyr.io/websocket, embed, image/png (for font loading)

---

## File Structure

| File | Responsibility |
|------|---------------|
| `go/main.go` | Entry point, HTTP server, WebSocket upgrade routing, game ticker |
| `go/config.go` | Config struct, flag/env parsing |
| `go/event.go` | InputEvent type, device/event-type/value constants |
| `go/palette.go` | 16-color C64 palette, hex strings |
| `go/bitmap.go` | Bitmap interface, SimpleBitmap |
| `go/sprite.go` | Sprite (bitmap + position + visibility) |
| `go/scrolling_bitmap.go` | ScrollingBitmap (viewport into larger bitmap) |
| `go/font.go` | Font type, pixel font character lookup |
| `go/text_bitmap.go` | TextBitmap: renders string to bitmap using Font |
| `go/loader.go` | BitmapLoader: loads bitmaps/sprites from embedded text files |
| `go/font_loader.go` | FontLoader: loads font from embedded JSON + PNG |
| `go/framebuffer.go` | FrameBuffer: double-buffered pixel grid with sprite rendering |
| `go/encoder.go` | JsonFrameEncoder: frame/frameDelta/frameInfo encoding |
| `go/gameloop.go` | GameLoop interface + BaseGameLoop shared logic |
| `go/gameloop_testimage.go` | TestImageScreen |
| `go/gameloop_pressstart.go` | PressStartToPlayLoop |
| `go/gameloop_main.go` | MainGameLoop (pong gameplay) |
| `go/gameloop_joystick.go` | JoystickTestLoop |
| `go/gameloop_makerfaire.go` | MakerFaireLoop |
| `go/server.go` | Server: connection manager, message handling, frame broadcast |
| `go/framebuffer_test.go` | FrameBuffer tests |
| `go/encoder_test.go` | JsonFrameEncoder tests |
| `go/loader_test.go` | BitmapLoader tests |
| `go/res/` | Symlink or copy of `../res/` (embedded resources) |
| `go/Dockerfile` | Multi-stage Docker build |

All Go code lives in `go/` subdirectory as `package main`. The `res/` directory is symlinked into `go/` for embedding.

---

### Task 1: Project scaffolding and dependencies

**Files:**
- Create: `go/go.mod`
- Create: `go/main.go` (minimal)
- Create: `go/config.go`
- Symlink: `go/res` -> `../res`

- [ ] **Step 1: Create go/ directory and symlink res/**

```bash
mkdir -p go
cd go && ln -s ../res res
```

- [ ] **Step 2: Initialize Go module and add dependency**

```bash
cd go
go mod init github.com/stigsb/pixelpong
go get nhooyr.io/websocket@latest
```

- [ ] **Step 3: Create config.go**

```go
package main

import (
	"flag"
	"fmt"
	"os"
	"strconv"
)

type Config struct {
	Port     int
	BindAddr string
	FPS      float64
	Width    int
	Height   int
}

func ParseConfig() Config {
	cfg := Config{
		Width:  47,
		Height: 27,
	}

	flag.IntVar(&cfg.Port, "p", 0, "server port")
	flag.Float64Var(&cfg.FPS, "f", 0, "frames per second")
	flag.Parse()

	if cfg.Port == 0 {
		cfg.Port = envInt("PONG_PORT", 4432)
	}
	if cfg.BindAddr == "" {
		cfg.BindAddr = envString("PONG_BIND_ADDR", "0.0.0.0")
	}
	if cfg.FPS == 0 {
		cfg.FPS = envFloat("PONG_FPS", 10.0)
	}

	return cfg
}

func envString(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}

func envInt(key string, fallback int) int {
	if v := os.Getenv(key); v != "" {
		if n, err := strconv.Atoi(v); err == nil {
			return n
		}
	}
	return fallback
}

func envFloat(key string, fallback float64) float64 {
	if v := os.Getenv(key); v != "" {
		if f, err := strconv.ParseFloat(v, 64); err == nil {
			return f
		}
	}
	return fallback
}
```

- [ ] **Step 4: Create minimal main.go**

```go
package main

import "fmt"

func main() {
	cfg := ParseConfig()
	fmt.Printf("Listening to port %d\n", cfg.Port)
}
```

- [ ] **Step 5: Verify it compiles**

Run: `cd go && go build ./...`
Expected: Success, no errors

- [ ] **Step 6: Commit**

```bash
git add go/
git commit -m "Add Go project scaffolding with config parsing"
```

---

### Task 2: Event types and color palette

**Files:**
- Create: `go/event.go`
- Create: `go/palette.go`

- [ ] **Step 1: Create event.go**

```go
package main

// Device constants
const (
	DeviceJoy1    = 1
	DeviceJoy2    = 2
	DeviceKeyboard = 3
)

// Event type constants
const (
	JoyAxisX  = 1
	JoyAxisY  = 2
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
```

- [ ] **Step 2: Create palette.go**

```go
package main

// Color index constants (C64-inspired)
const (
	ColorBlack      = 0
	ColorWhite      = 1
	ColorRed        = 2
	ColorCyan       = 3
	ColorPurple     = 4
	ColorGreen      = 5
	ColorBlue       = 6
	ColorYellow     = 7
	ColorOrange     = 8
	ColorBrown      = 9
	ColorLightRed   = 10
	ColorDarkGrey   = 11
	ColorGrey       = 12
	ColorLightGreen = 13
	ColorLightBlue  = 14
	ColorLightGrey  = 15

	ColorTransparent = -1
)

var Palette = []string{
	"#000000", // Black
	"#fcf9fc", // White
	"#933a4c", // Red
	"#b6fafa", // Cyan
	"#d27ded", // Purple
	"#6acf6f", // Green
	"#4f44d8", // Blue
	"#fbfb8b", // Yellow
	"#d89c5b", // Orange
	"#7f5307", // Brown
	"#ef839f", // Light Red
	"#575753", // Dark Grey
	"#a3a7a7", // Grey
	"#b7fbbf", // Light Green
	"#a397ff", // Light Blue
	"#d0d0d0", // Light Grey
}
```

- [ ] **Step 3: Verify it compiles**

Run: `cd go && go build ./...`
Expected: Success

- [ ] **Step 4: Commit**

```bash
git add go/event.go go/palette.go
git commit -m "Add event types and color palette"
```

---

### Task 3: Bitmap interface and SimpleBitmap

**Files:**
- Create: `go/bitmap.go`
- Create: `go/sprite.go`

- [ ] **Step 1: Create bitmap.go**

```go
package main

// Bitmap represents a rectangular grid of color-indexed pixels.
type Bitmap interface {
	Width() int
	Height() int
	Pixels() []int
}

// SimpleBitmap is a basic bitmap backed by a flat pixel array.
type SimpleBitmap struct {
	W, H   int
	pixels []int
}

func NewSimpleBitmap(w, h int, pixels []int) *SimpleBitmap {
	return &SimpleBitmap{W: w, H: h, pixels: pixels}
}

func (b *SimpleBitmap) Width() int    { return b.W }
func (b *SimpleBitmap) Height() int   { return b.H }
func (b *SimpleBitmap) Pixels() []int { return b.pixels }
```

- [ ] **Step 2: Create sprite.go**

```go
package main

// Sprite wraps a Bitmap with a position and visibility flag.
type Sprite struct {
	bitmap  Bitmap
	X, Y    int
	Visible bool
}

func NewSprite(bm Bitmap, x, y int) *Sprite {
	return &Sprite{bitmap: bm, X: x, Y: y, Visible: true}
}

func (s *Sprite) MoveTo(x, y int) {
	s.X = x
	s.Y = y
}

func (s *Sprite) Bitmap() Bitmap {
	return s.bitmap
}
```

- [ ] **Step 3: Verify it compiles**

Run: `cd go && go build ./...`
Expected: Success

- [ ] **Step 4: Commit**

```bash
git add go/bitmap.go go/sprite.go
git commit -m "Add Bitmap interface, SimpleBitmap, and Sprite"
```

---

### Task 4: ScrollingBitmap and TextBitmap

**Files:**
- Create: `go/scrolling_bitmap.go`
- Create: `go/font.go`
- Create: `go/text_bitmap.go`

- [ ] **Step 1: Create scrolling_bitmap.go**

```go
package main

// ScrollingBitmap provides a viewport into a larger bitmap.
type ScrollingBitmap struct {
	bitmap      Bitmap
	W, H        int
	XOffset     int
	YOffset     int
	blankPixels []int
}

func NewScrollingBitmap(bm Bitmap, w, h, xOffset, yOffset int) *ScrollingBitmap {
	blank := make([]int, w*h)
	for i := range blank {
		blank[i] = ColorTransparent
	}
	return &ScrollingBitmap{
		bitmap:      bm,
		W:           w,
		H:           h,
		XOffset:     xOffset,
		YOffset:     yOffset,
		blankPixels: blank,
	}
}

func (sb *ScrollingBitmap) Width() int  { return sb.W }
func (sb *ScrollingBitmap) Height() int { return sb.H }

func (sb *ScrollingBitmap) Pixels() []int {
	origW := sb.bitmap.Width()
	origH := sb.bitmap.Height()
	origPixels := sb.bitmap.Pixels()
	pixels := make([]int, len(sb.blankPixels))
	copy(pixels, sb.blankPixels)

	maxX := sb.W
	if origW-sb.XOffset < maxX {
		maxX = origW - sb.XOffset
	}
	maxY := sb.H
	if origH-sb.YOffset < maxY {
		maxY = origH - sb.YOffset
	}
	for y := 0; y < maxY; y++ {
		oy := sb.YOffset + y
		if oy < 0 {
			continue
		}
		for x := 0; x < maxX; x++ {
			ox := sb.XOffset + x
			if ox < 0 {
				continue
			}
			pixels[y*sb.W+x] = origPixels[oy*origW+ox]
		}
	}
	return pixels
}

func (sb *ScrollingBitmap) ScrollTo(x, y int) {
	sb.XOffset = x
	sb.YOffset = y
}
```

- [ ] **Step 2: Create font.go**

```go
package main

// Font is a fixed-width monochrome pixel font.
type Font struct {
	CharBitmaps map[int][]int // character code -> pixel array
	W           int           // character width
	H           int           // character height
	BlankChar   int           // character code for unknown chars
}

const (
	FontBG = 0
	FontFG = 1
)

func (f *Font) PixelsForChar(charCode int) []int {
	if px, ok := f.CharBitmaps[charCode]; ok {
		return px
	}
	return f.CharBitmaps[f.BlankChar]
}

func (f *Font) Width() int  { return f.W }
func (f *Font) Height() int { return f.H }
```

- [ ] **Step 3: Create text_bitmap.go**

```go
package main

// NewTextBitmap creates a bitmap that renders the given text string using the font.
func NewTextBitmap(font *Font, text string, color int, spacing int) *SimpleBitmap {
	cw := font.W
	ch := font.H
	numChars := len(text)
	if numChars == 0 {
		return NewSimpleBitmap(0, ch, nil)
	}
	fullWidth := (cw * numChars) + ((numChars - 1) * spacing)
	fullHeight := ch
	pixels := make([]int, fullWidth*fullHeight)

	for i := 0; i < numChars; i++ {
		charCode := int(text[i])
		cox := (cw + spacing) * i
		charPixels := font.PixelsForChar(charCode)
		for y := 0; y < ch; y++ {
			for x := 0; x < cw; x++ {
				if charPixels[y*cw+x] != 0 {
					pixels[fullWidth*y+cox+x] = color
				}
			}
		}
	}
	return NewSimpleBitmap(fullWidth, fullHeight, pixels)
}
```

- [ ] **Step 4: Verify it compiles**

Run: `cd go && go build ./...`
Expected: Success

- [ ] **Step 5: Commit**

```bash
git add go/scrolling_bitmap.go go/font.go go/text_bitmap.go
git commit -m "Add ScrollingBitmap, Font, and TextBitmap"
```

---

### Task 5: BitmapLoader and FontLoader

**Files:**
- Create: `go/loader.go`
- Create: `go/font_loader.go`
- Create: `go/loader_test.go`

- [ ] **Step 1: Create loader.go**

```go
package main

import (
	"embed"
	"fmt"
	"strings"
)

//go:embed res
var resources embed.FS

var colorMap = map[byte]int{
	' ': ColorTransparent,
	'.': ColorBlack,
	'0': ColorBlack,
	'#': ColorWhite,
	'1': ColorWhite,
	'2': ColorRed,
	'3': ColorCyan,
	'4': ColorPurple,
	'5': ColorGreen,
	'6': ColorBlue,
	'7': ColorYellow,
	'8': ColorOrange,
	'9': ColorBrown,
	'a': ColorLightRed,
	'b': ColorDarkGrey,
	'c': ColorGrey,
	'd': ColorLightGreen,
	'e': ColorLightBlue,
	'f': ColorLightGrey,
}

// BitmapLoader loads bitmaps from embedded text files.
type BitmapLoader struct {
	paths []string
	cache map[string]*SimpleBitmap
}

func NewBitmapLoader(paths []string) *BitmapLoader {
	return &BitmapLoader{paths: paths, cache: make(map[string]*SimpleBitmap)}
}

func (bl *BitmapLoader) LoadBitmap(name string) (*SimpleBitmap, error) {
	if bm, ok := bl.cache[name]; ok {
		return bm, nil
	}
	for _, dir := range bl.paths {
		path := dir + "/" + name + ".txt"
		data, err := resources.ReadFile(path)
		if err != nil {
			continue
		}
		bm := parseBitmapData(data)
		bl.cache[name] = bm
		return bm, nil
	}
	return nil, fmt.Errorf("bitmap not found: %s", name)
}

func (bl *BitmapLoader) LoadSprite(name string, x, y int) (*Sprite, error) {
	bm, err := bl.LoadBitmap(name)
	if err != nil {
		return nil, err
	}
	return NewSprite(bm, x, y), nil
}

func parseBitmapData(data []byte) *SimpleBitmap {
	rawLines := strings.Split(string(data), "\n")
	var lines []string
	width := 0
	for _, line := range rawLines {
		line = strings.TrimRight(line, "|\r")
		if len(line) > width {
			width = len(line)
		}
		lines = append(lines, line)
	}
	// Remove trailing empty lines
	for len(lines) > 0 && lines[len(lines)-1] == "" {
		lines = lines[:len(lines)-1]
	}
	height := len(lines)
	pixels := make([]int, width*height)
	for i := range pixels {
		pixels[i] = ColorTransparent
	}
	for y, line := range lines {
		for x := 0; x < len(line) && x < width; x++ {
			if c, ok := colorMap[line[x]]; ok {
				pixels[y*width+x] = c
			}
		}
	}
	return NewSimpleBitmap(width, height, pixels)
}
```

- [ ] **Step 2: Create font_loader.go**

```go
package main

import (
	"encoding/json"
	"fmt"
	"image"
	"image/color"
	_ "image/png"
)

type fontMeta struct {
	Width          int      `json:"width"`
	Height         int      `json:"height"`
	BlankChar      string   `json:"blankChar"`
	PixelColor     string   `json:"pixelColor"`
	CharSpacing    [2]int   `json:"charSpacing"`
	CharacterLines []string `json:"characterLines"`
}

func LoadFont(name string, fontDir string) (*Font, error) {
	// Load JSON metadata
	jsonData, err := resources.ReadFile(fontDir + "/" + name + ".json")
	if err != nil {
		return nil, fmt.Errorf("reading font metadata: %w", err)
	}
	var meta fontMeta
	if err := json.Unmarshal(jsonData, &meta); err != nil {
		return nil, fmt.Errorf("parsing font metadata: %w", err)
	}

	// Load PNG image
	pngFile, err := resources.Open(fontDir + "/" + name + ".png")
	if err != nil {
		return nil, fmt.Errorf("opening font image: %w", err)
	}
	defer pngFile.Close()
	img, _, err := image.Decode(pngFile)
	if err != nil {
		return nil, fmt.Errorf("decoding font image: %w", err)
	}

	// Parse pixel color from hex string like "#333333"
	pixelColor := parseHexColor(meta.PixelColor)

	charBitmaps := make(map[int][]int)
	charPixels := meta.Width * meta.Height
	oy := 0

	for _, charLine := range meta.CharacterLines {
		ox := 0
		for i := 0; i < len(charLine); i++ {
			ch := charLine[i]
			if string(ch) == meta.BlankChar {
				ox += meta.Width + meta.CharSpacing[0]
				continue
			}
			pixels := make([]int, charPixels)
			for y := 0; y < meta.Height; y++ {
				for x := 0; x < meta.Width; x++ {
					r, g, b, _ := img.At(ox+x, oy+y).RGBA()
					pr, pg, pb, _ := pixelColor.RGBA()
					if r == pr && g == pg && b == pb {
						pixels[y*meta.Width+x] = FontFG
					}
				}
			}
			charBitmaps[int(ch)] = pixels
			ox += meta.Width + meta.CharSpacing[0]
		}
		oy += meta.Height + meta.CharSpacing[1]
	}

	return &Font{
		CharBitmaps: charBitmaps,
		W:           meta.Width,
		H:           meta.Height,
		BlankChar:   int(meta.BlankChar[0]),
	}, nil
}

func parseHexColor(hex string) color.RGBA {
	if len(hex) == 7 && hex[0] == '#' {
		hex = hex[1:]
	}
	var r, g, b uint8
	fmt.Sscanf(hex, "%02x%02x%02x", &r, &g, &b)
	return color.RGBA{R: r, G: g, B: b, A: 255}
}
```

- [ ] **Step 3: Create loader_test.go**

```go
package main

import "testing"

func TestParseBitmapData(t *testing.T) {
	data := []byte(".#.\n#.#\n.#.")
	bm := parseBitmapData(data)
	if bm.Width() != 3 {
		t.Errorf("expected width 3, got %d", bm.Width())
	}
	if bm.Height() != 3 {
		t.Errorf("expected height 3, got %d", bm.Height())
	}
	// '#' maps to White (1), '.' maps to Black (0)
	pixels := bm.Pixels()
	if pixels[0] != ColorBlack {
		t.Errorf("expected pixel 0 to be Black, got %d", pixels[0])
	}
	if pixels[1] != ColorWhite {
		t.Errorf("expected pixel 1 to be White, got %d", pixels[1])
	}
}

func TestBitmapLoaderLoadBitmap(t *testing.T) {
	loader := NewBitmapLoader([]string{"res/sprites"})
	bm, err := loader.LoadBitmap("ball")
	if err != nil {
		t.Fatalf("failed to load ball bitmap: %v", err)
	}
	if bm.Width() == 0 || bm.Height() == 0 {
		t.Errorf("expected non-zero dimensions, got %dx%d", bm.Width(), bm.Height())
	}
}

func TestBitmapLoaderCaching(t *testing.T) {
	loader := NewBitmapLoader([]string{"res/sprites"})
	bm1, _ := loader.LoadBitmap("ball")
	bm2, _ := loader.LoadBitmap("ball")
	if bm1 != bm2 {
		t.Error("expected same pointer from cache")
	}
}

func TestBitmapLoaderNotFound(t *testing.T) {
	loader := NewBitmapLoader([]string{"res/sprites"})
	_, err := loader.LoadBitmap("nonexistent")
	if err == nil {
		t.Error("expected error for missing bitmap")
	}
}
```

- [ ] **Step 4: Run tests**

Run: `cd go && go test -v -run TestParseBitmap -count=1`
Expected: PASS

Run: `cd go && go test -v -run TestBitmapLoader -count=1`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add go/loader.go go/font_loader.go go/loader_test.go
git commit -m "Add BitmapLoader and FontLoader with tests"
```

---

### Task 6: FrameBuffer

**Files:**
- Create: `go/framebuffer.go`
- Create: `go/framebuffer_test.go`

- [ ] **Step 1: Write failing test for FrameBuffer**

```go
package main

import "testing"

func TestFrameBufferNew(t *testing.T) {
	fb := NewFrameBuffer(7, 3)
	if fb.Width() != 7 {
		t.Errorf("expected width 7, got %d", fb.Width())
	}
	if fb.Height() != 3 {
		t.Errorf("expected height 3, got %d", fb.Height())
	}
	frame := fb.Frame()
	for i, p := range frame {
		if p != 0 {
			t.Errorf("expected pixel %d to be 0, got %d", i, p)
		}
	}
}

func TestFrameBufferSetGetPixel(t *testing.T) {
	fb := NewFrameBuffer(7, 3)
	fb.SetPixel(1, 2, 1)
	if fb.GetPixel(1, 2) != 1 {
		t.Error("expected pixel (1,2) to be 1")
	}
	if fb.GetPixel(2, 1) != 0 {
		t.Error("expected pixel (2,1) to be 0")
	}
}

func TestFrameBufferGetAndSwitch(t *testing.T) {
	fb := NewFrameBuffer(7, 3)
	fb.SetPixel(0, 0, 1)
	frame := fb.GetAndSwitchFrame()
	if frame[0] != 1 {
		t.Error("expected pixel 0 to be 1 in returned frame")
	}
	if fb.Frame()[0] != 0 {
		t.Error("expected pixel 0 to be 0 after switch")
	}
}

func TestFrameBufferDrawBitmapAt(t *testing.T) {
	fb := NewFrameBuffer(4, 4)
	bm := NewSimpleBitmap(2, 2, []int{1, 2, 3, 4})
	fb.DrawBitmapAt(bm, 1, 1)
	frame := fb.GetAndSwitchFrame()
	// Pixel at (1,1) should be 1
	if frame[1*4+1] != 1 {
		t.Errorf("expected pixel at (1,1) to be 1, got %d", frame[1*4+1])
	}
	// Pixel at (2,2) should be 4
	if frame[2*4+2] != 4 {
		t.Errorf("expected pixel at (2,2) to be 4, got %d", frame[2*4+2])
	}
}

func TestFrameBufferSetBackground(t *testing.T) {
	fb := NewFrameBuffer(3, 1)
	bg := []int{5, 5, 5}
	fb.SetBackgroundFrame(bg)
	fb.SetPixel(0, 0, 1)
	_ = fb.GetAndSwitchFrame()
	// After switch, should revert to background
	frame := fb.Frame()
	if frame[0] != 5 {
		t.Errorf("expected pixel 0 to be 5 (background), got %d", frame[0])
	}
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd go && go test -v -run TestFrameBuffer -count=1`
Expected: FAIL (FrameBuffer not defined)

- [ ] **Step 3: Create framebuffer.go**

```go
package main

// FrameBuffer is a double-buffered pixel grid that supports sprite rendering.
type FrameBuffer struct {
	width, height int
	background    []int
	current       []int
	bitmaps       []bitmapDraw
}

type bitmapDraw struct {
	bm   Bitmap
	x, y int
}

func NewFrameBuffer(width, height int) *FrameBuffer {
	size := width * height
	bg := make([]int, size)
	cur := make([]int, size)
	return &FrameBuffer{
		width:      width,
		height:     height,
		background: bg,
		current:    cur,
	}
}

func (fb *FrameBuffer) Width() int  { return fb.width }
func (fb *FrameBuffer) Height() int { return fb.height }

func (fb *FrameBuffer) GetPixel(x, y int) int {
	return fb.current[y*fb.width+x]
}

func (fb *FrameBuffer) SetPixel(x, y, color int) {
	if x < 0 || x >= fb.width || y < 0 || y >= fb.height {
		return
	}
	fb.current[y*fb.width+x] = color
}

func (fb *FrameBuffer) Frame() []int {
	return fb.current
}

func (fb *FrameBuffer) GetAndSwitchFrame() []int {
	fb.renderBitmaps()
	frame := fb.current
	fb.newFrame()
	return frame
}

func (fb *FrameBuffer) SetBackgroundFrame(frame []int) {
	fb.background = make([]int, len(frame))
	copy(fb.background, frame)
}

func (fb *FrameBuffer) DrawBitmapAt(bm Bitmap, x, y int) {
	fb.bitmaps = append(fb.bitmaps, bitmapDraw{bm: bm, x: x, y: y})
}

func (fb *FrameBuffer) newFrame() {
	fb.current = make([]int, fb.width*fb.height)
	copy(fb.current, fb.background)
	fb.bitmaps = fb.bitmaps[:0]
}

func (fb *FrameBuffer) renderBitmaps() {
	for _, bd := range fb.bitmaps {
		pixels := bd.bm.Pixels()
		w := bd.bm.Width()
		h := bd.bm.Height()
		for x := 0; x < w; x++ {
			xx := bd.x + x
			if xx < 0 || xx >= fb.width {
				continue
			}
			for y := 0; y < h; y++ {
				yy := bd.y + y
				if yy < 0 || yy >= fb.height {
					continue
				}
				pixel := pixels[y*w+x]
				if pixel == ColorTransparent {
					continue
				}
				fb.SetPixel(xx, yy, pixel)
			}
		}
	}
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd go && go test -v -run TestFrameBuffer -count=1`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add go/framebuffer.go go/framebuffer_test.go
git commit -m "Add FrameBuffer with double-buffering and sprite rendering"
```

---

### Task 7: JSON Frame Encoder

**Files:**
- Create: `go/encoder.go`
- Create: `go/encoder_test.go`

- [ ] **Step 1: Write failing tests**

```go
package main

import (
	"encoding/json"
	"testing"
)

func TestEncoderFullFrame(t *testing.T) {
	enc := NewJsonFrameEncoder(7, 3)
	frame := make([]int, 21)
	frame[2] = 1
	frame[3] = 1
	frame[9] = 1
	frame[10] = 1

	result := enc.EncodeFrame(frame)
	if result == nil {
		t.Fatal("expected non-nil result")
	}
	var msg map[string]interface{}
	if err := json.Unmarshal(result, &msg); err != nil {
		t.Fatalf("invalid JSON: %v", err)
	}
	if _, ok := msg["frame"]; !ok {
		t.Error("expected 'frame' key in message")
	}
}

func TestEncoderNoChange(t *testing.T) {
	enc := NewJsonFrameEncoder(7, 3)
	frame := make([]int, 21)
	enc.EncodeFrame(frame)        // first frame
	result := enc.EncodeFrame(frame) // same frame
	if result != nil {
		t.Error("expected nil for unchanged frame")
	}
}

func TestEncoderDelta(t *testing.T) {
	enc := NewJsonFrameEncoder(7, 3)
	frame1 := make([]int, 21)
	enc.EncodeFrame(frame1) // first frame

	// Change only 2 pixels (< 1/3 of 21 = 7)
	frame2 := make([]int, 21)
	frame2[0] = 1
	frame2[1] = 1

	result := enc.EncodeFrame(frame2)
	if result == nil {
		t.Fatal("expected non-nil result")
	}
	var msg map[string]interface{}
	if err := json.Unmarshal(result, &msg); err != nil {
		t.Fatalf("invalid JSON: %v", err)
	}
	if _, ok := msg["frameDelta"]; !ok {
		t.Error("expected 'frameDelta' key for small change")
	}
}

func TestEncoderFrameInfo(t *testing.T) {
	result := EncodeFrameInfo(47, 27)
	var msg map[string]interface{}
	if err := json.Unmarshal(result, &msg); err != nil {
		t.Fatalf("invalid JSON: %v", err)
	}
	fi, ok := msg["frameInfo"].(map[string]interface{})
	if !ok {
		t.Fatal("expected frameInfo object")
	}
	if fi["width"].(float64) != 47 {
		t.Error("expected width 47")
	}
	if fi["height"].(float64) != 27 {
		t.Error("expected height 27")
	}
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd go && go test -v -run TestEncoder -count=1`
Expected: FAIL (JsonFrameEncoder not defined)

- [ ] **Step 3: Create encoder.go**

```go
package main

import (
	"encoding/json"
	"strconv"
)

// JsonFrameEncoder encodes frames as JSON, supporting full frame and delta encoding.
type JsonFrameEncoder struct {
	width     int
	height    int
	prevFrame []int
}

func NewJsonFrameEncoder(width, height int) *JsonFrameEncoder {
	size := width * height
	prev := make([]int, size)
	return &JsonFrameEncoder{
		width:     width,
		height:    height,
		prevFrame: prev,
	}
}

func (e *JsonFrameEncoder) EncodeFrame(frame []int) []byte {
	// Count differences
	var diffCount int
	for i := range frame {
		if frame[i] != e.prevFrame[i] {
			diffCount++
		}
	}

	// Save current as previous
	prev := e.prevFrame
	e.prevFrame = make([]int, len(frame))
	copy(e.prevFrame, frame)

	if diffCount == 0 {
		return nil
	}

	if diffCount < len(frame)/3 {
		// Send delta - only changed pixels
		delta := make(map[string]int)
		for i := range frame {
			if frame[i] != prev[i] {
				delta[strconv.Itoa(i)] = frame[i]
			}
		}
		data, _ := json.Marshal(map[string]interface{}{"frameDelta": delta})
		return data
	}

	// Send full frame - only non-background pixels
	pixels := make(map[string]int)
	for i, c := range frame {
		if c != ColorBlack {
			pixels[strconv.Itoa(i)] = c
		}
	}
	data, _ := json.Marshal(map[string]interface{}{"frame": pixels})
	return data
}

func EncodeFrameInfo(width, height int) []byte {
	info := map[string]interface{}{
		"frameInfo": map[string]interface{}{
			"width":   width,
			"height":  height,
			"palette": Palette,
		},
	}
	data, _ := json.Marshal(info)
	return data
}
```

- [ ] **Step 4: Run tests**

Run: `cd go && go test -v -run TestEncoder -count=1`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add go/encoder.go go/encoder_test.go
git commit -m "Add JSON frame encoder with delta support"
```

---

### Task 8: GameLoop interface and BaseGameLoop

**Files:**
- Create: `go/gameloop.go`

- [ ] **Step 1: Create gameloop.go**

```go
package main

// GameLoop is the interface for game state machines.
type GameLoop interface {
	// OnEnter is called when the server switches to this game loop.
	OnEnter()
	// OnFrameUpdate is called each tick to update game state and draw.
	OnFrameUpdate()
	// OnEvent processes a single input event.
	OnEvent(event InputEvent)
}

// SwitchRequester can signal that the game loop wants to transition.
type SwitchRequester interface {
	// NextLoop returns the name of the game loop to switch to, or "" to stay.
	NextLoop() string
}

// BaseGameLoop provides common game loop functionality.
type BaseGameLoop struct {
	fb           *FrameBuffer
	loader       *BitmapLoader
	fontLoader   *Font
	background   *SimpleBitmap
	sprites      []*Sprite
	nextLoop     string
}

func (b *BaseGameLoop) init(fb *FrameBuffer, loader *BitmapLoader, font *Font) {
	b.fb = fb
	b.loader = loader
	b.fontLoader = font
	b.sprites = nil
	b.nextLoop = ""
}

func (b *BaseGameLoop) OnEnter() {
	if b.background != nil {
		b.fb.SetBackgroundFrame(b.background.Pixels())
	}
}

func (b *BaseGameLoop) OnFrameUpdate() {
	b.renderVisibleSprites()
}

func (b *BaseGameLoop) AddSprite(s *Sprite) {
	b.sprites = append(b.sprites, s)
}

func (b *BaseGameLoop) renderVisibleSprites() {
	for _, s := range b.sprites {
		if s.Visible {
			b.fb.DrawBitmapAt(s.Bitmap(), s.X, s.Y)
		}
	}
}

func (b *BaseGameLoop) NextLoop() string {
	nl := b.nextLoop
	b.nextLoop = ""
	return nl
}

func (b *BaseGameLoop) RequestSwitch(name string) {
	b.nextLoop = name
}
```

- [ ] **Step 2: Verify it compiles**

Run: `cd go && go build ./...`
Expected: Success

- [ ] **Step 3: Commit**

```bash
git add go/gameloop.go
git commit -m "Add GameLoop interface and BaseGameLoop"
```

---

### Task 9: TestImageScreen and PressStartToPlayLoop

**Files:**
- Create: `go/gameloop_testimage.go`
- Create: `go/gameloop_pressstart.go`

- [ ] **Step 1: Create gameloop_testimage.go**

```go
package main

type TestImageScreen struct {
	BaseGameLoop
}

func NewTestImageScreen(fb *FrameBuffer, loader *BitmapLoader, font *Font) *TestImageScreen {
	t := &TestImageScreen{}
	t.init(fb, loader, font)
	bm, err := loader.LoadBitmap("test_image")
	if err != nil {
		panic("failed to load test_image: " + err.Error())
	}
	t.background = bm
	return t
}

func (t *TestImageScreen) OnEvent(event InputEvent) {
	if event.EventType == JoyButton1 && event.Value == ButtonNeutral {
		t.RequestSwitch("pressstart")
	}
}
```

- [ ] **Step 2: Create gameloop_pressstart.go**

```go
package main

import "time"

type PressStartToPlayLoop struct {
	BaseGameLoop
	pressStartFrame []int
	toPlayFrame     []int
	enterTime       time.Time
	previousElapsed int
}

func NewPressStartToPlayLoop(fb *FrameBuffer, loader *BitmapLoader, font *Font) *PressStartToPlayLoop {
	p := &PressStartToPlayLoop{}
	p.init(fb, loader, font)
	bm1, err := loader.LoadBitmap("press_start")
	if err != nil {
		panic("failed to load press_start: " + err.Error())
	}
	p.pressStartFrame = bm1.Pixels()
	bm2, err := loader.LoadBitmap("to_play")
	if err != nil {
		panic("failed to load to_play: " + err.Error())
	}
	p.toPlayFrame = bm2.Pixels()
	return p
}

func (p *PressStartToPlayLoop) OnEnter() {
	p.fb.SetBackgroundFrame(p.pressStartFrame)
	p.enterTime = time.Now()
	p.previousElapsed = 0
}

func (p *PressStartToPlayLoop) OnFrameUpdate() {
	elapsed := int(time.Since(p.enterTime).Seconds())
	if elapsed > p.previousElapsed {
		switch elapsed % 4 {
		case 0:
			p.fb.SetBackgroundFrame(p.pressStartFrame)
		case 2:
			p.fb.SetBackgroundFrame(p.toPlayFrame)
		}
	}
	p.previousElapsed = elapsed
}

func (p *PressStartToPlayLoop) OnEvent(event InputEvent) {
	if event.EventType == JoyButton1 && event.Value == ButtonNeutral {
		p.RequestSwitch("maingame")
	}
}
```

- [ ] **Step 3: Verify it compiles**

Run: `cd go && go build ./...`
Expected: Success

- [ ] **Step 4: Commit**

```bash
git add go/gameloop_testimage.go go/gameloop_pressstart.go
git commit -m "Add TestImageScreen and PressStartToPlayLoop"
```

---

### Task 10: MainGameLoop

**Files:**
- Create: `go/gameloop_main.go`

- [ ] **Step 1: Create gameloop_main.go**

```go
package main

import "time"

const (
	ballSpeed             = 3.0
	paddleSpeed           = 10.0
	paddleCenterY         = 12.0
	paddleDistToSides     = 1.0
	ballSpeedupEveryNSecs = 10
	ballSpeedupFactor     = 1.10
	frameEdgeSize         = 1.0
)

const (
	sideLeft  = 0
	sideRight = 1
	edgeTop   = 0
	edgeBottom = 1
	axisX     = 0
	axisY     = 1
)

const (
	stateInitializing = iota
	stateWaiting
	statePlaying
	stateGameOver
)

var inputDevices = map[int]int{
	DeviceJoy1: sideLeft,
	DeviceJoy2: sideRight,
}

type MainGameLoop struct {
	BaseGameLoop
	displayWidth  int
	displayHeight int

	paddles     [2]*Sprite
	ball        *Sprite
	paddleHeight int
	paddleWidth  int
	ballHeight   int
	ballWidth    int

	paddlePositions [2]float64
	currentYAxis    [2]int
	lastYAxisUpdate [2]float64
	paddlePosX      [2]float64
	ballPaddleLimit [2]float64
	ballEdgeLimit   [2]float64
	paddleMinY      float64
	paddleMaxY      float64

	ballPos   [2]float64
	ballDelta [2]float64

	gameState    int
	winningSide  int

	initTimestamp    float64
	startTimestamp   float64
	frameTimestamp   float64
	lastSpeedupTime  float64
	approxFrameTime  float64
}

func NewMainGameLoop(fb *FrameBuffer, loader *BitmapLoader, font *Font) *MainGameLoop {
	m := &MainGameLoop{}
	m.init(fb, loader, font)
	m.initializeGame(fb, loader)
	return m
}

func (m *MainGameLoop) initializeGame(fb *FrameBuffer, loader *BitmapLoader) {
	bg, _ := loader.LoadBitmap("main_game")
	m.background = bg
	m.displayWidth = fb.Width()
	m.displayHeight = fb.Height()

	leftPaddle, _ := loader.LoadSprite("paddle", 0, 0)
	rightPaddle, _ := loader.LoadSprite("paddle", 0, 0)
	m.paddles = [2]*Sprite{leftPaddle, rightPaddle}
	m.AddSprite(leftPaddle)
	m.AddSprite(rightPaddle)

	m.paddleHeight = leftPaddle.Bitmap().Height()
	m.paddleWidth = leftPaddle.Bitmap().Width()
	m.paddleMinY = frameEdgeSize
	m.paddleMaxY = float64(m.displayHeight) - float64(m.paddleHeight) - frameEdgeSize

	ball, _ := loader.LoadSprite("ball", 0, 0)
	m.ball = ball
	m.AddSprite(ball)
	m.ballHeight = ball.Bitmap().Height()
	m.ballWidth = ball.Bitmap().Width()

	m.paddlePosX[sideLeft] = 1.0
	m.paddlePosX[sideRight] = float64(m.displayWidth) - 1.0 - float64(m.paddleWidth)

	m.ballPaddleLimit[sideLeft] = m.paddlePosX[sideLeft] + float64(m.paddleWidth)
	m.ballPaddleLimit[sideRight] = m.paddlePosX[sideRight] - float64(m.paddleWidth)

	m.ballEdgeLimit[edgeTop] = 1.0
	m.ballEdgeLimit[edgeBottom] = float64(m.displayHeight) - float64(m.ballHeight)

	m.frameTimestamp = float64(time.Now().UnixMicro()) / 1e6
	m.gameState = stateInitializing
}

func (m *MainGameLoop) resetGame() {
	m.lastYAxisUpdate = [2]float64{}
	m.currentYAxis = [2]int{AxisNeutral, AxisNeutral}
	m.winningSide = -1

	paddleMiddleY := float64(m.displayHeight)/2.0 - float64(m.paddleHeight)/2.0
	m.paddlePositions = [2]float64{paddleMiddleY, paddleMiddleY}

	m.ballPos = [2]float64{m.ballPaddleLimit[sideLeft], paddleCenterY}
	m.ballDelta = [2]float64{0, 0}

	m.gameState = stateInitializing
	m.initTimestamp = 0
	m.startTimestamp = 0

	m.updateBallSprite()
	m.updatePaddleSprites()
}

func (m *MainGameLoop) OnEnter() {
	m.BaseGameLoop.OnEnter()
	m.resetGame()
}

func (m *MainGameLoop) OnEvent(event InputEvent) {
	switch m.gameState {
	case stateWaiting:
		if event.EventType == JoyButton1 && event.Value == ButtonNeutral {
			m.startGame()
		}
	case statePlaying:
		if event.EventType == JoyAxisY {
			if event.Value == AxisNeutral {
				m.updatePaddleForDevice(event.Device)
			}
			paddle, ok := inputDevices[event.Device]
			if ok {
				m.currentYAxis[paddle] = event.Value
			}
		}
	case stateGameOver:
		if event.EventType == JoyButton1 && event.Value == ButtonNeutral {
			m.resetGame()
		}
	}
}

func (m *MainGameLoop) OnFrameUpdate() {
	m.frameTimestamp = float64(time.Now().UnixMicro()) / 1e6

	switch m.gameState {
	case stateInitializing:
		if m.initTimestamp == 0 {
			m.initTimestamp = m.frameTimestamp
		} else {
			m.approxFrameTime = m.frameTimestamp - m.initTimestamp
			m.gameState = stateWaiting
		}
	case statePlaying:
		for device := range inputDevices {
			m.updatePaddleForDevice(device)
		}
		m.updatePaddleSprites()
		m.updateBallPosition()
	}
	m.BaseGameLoop.OnFrameUpdate()
}

func (m *MainGameLoop) startGame() {
	m.ballDelta[axisX] = paddleSpeed * m.approxFrameTime
	m.ballDelta[axisY] = paddleSpeed * m.approxFrameTime
	m.gameState = statePlaying
	m.startTimestamp = m.frameTimestamp
	m.lastSpeedupTime = m.frameTimestamp
}

func (m *MainGameLoop) updateBallPosition() {
	m.ballPos[axisX] += m.ballDelta[axisX]
	m.ballPos[axisY] += m.ballDelta[axisY]

	if m.ballPos[axisY] <= m.ballEdgeLimit[edgeTop] {
		m.bounceBallOnEdge(edgeTop)
	} else if m.ballPos[axisY] >= m.ballEdgeLimit[edgeBottom] {
		m.bounceBallOnEdge(edgeBottom)
	}

	if m.ballPos[axisX] <= m.ballPaddleLimit[sideLeft] {
		if m.ballHitPaddle(sideLeft) {
			m.bounceBallOnPaddle(sideLeft)
		} else {
			m.playerWon(sideRight)
		}
	} else if m.ballPos[axisX] >= m.ballPaddleLimit[sideRight] {
		if m.ballHitPaddle(sideRight) {
			m.bounceBallOnPaddle(sideRight)
		} else {
			m.playerWon(sideLeft)
		}
	}

	m.updateBallSprite()
}

func (m *MainGameLoop) updateBallSprite() {
	m.ball.MoveTo(int(m.ballPos[axisX]), int(m.ballPos[axisY]))
}

func (m *MainGameLoop) updatePaddleSprites() {
	for paddle, ypos := range m.paddlePositions {
		m.paddles[paddle].MoveTo(int(m.paddlePosX[paddle]), int(ypos))
	}
}

func (m *MainGameLoop) updatePaddleForDevice(device int) {
	paddle, ok := inputDevices[device]
	if !ok {
		return
	}
	now := m.frameTimestamp
	elapsed := now - m.lastYAxisUpdate[paddle]
	newPos := m.paddlePositions[paddle] + paddleSpeed*elapsed*float64(m.currentYAxis[paddle])

	if newPos < m.paddleMinY {
		newPos = m.paddleMinY
	} else if newPos > m.paddleMaxY {
		newPos = m.paddleMaxY
	}
	m.paddlePositions[paddle] = newPos
	m.lastYAxisUpdate[paddle] = now
}

func (m *MainGameLoop) playerWon(side int) {
	m.gameState = stateGameOver
	m.winningSide = side
}

func (m *MainGameLoop) ballHitPaddle(paddle int) bool {
	ballY := m.ballPos[axisY]
	paddleYMin := m.paddlePositions[paddle] - float64(m.ballHeight)
	paddleYMax := m.paddlePositions[paddle] + float64(m.paddleHeight) + float64(m.ballHeight)
	return ballY > paddleYMin && ballY < paddleYMax
}

func (m *MainGameLoop) bounceBallOnPaddle(paddle int) {
	bounceBack := m.ballPaddleLimit[paddle] - m.ballPos[axisX]
	m.ballPos[axisX] = m.ballPaddleLimit[paddle] + bounceBack
	m.ballDelta[axisX] *= -1.0
	m.maybeSpeedUpBall()
}

func (m *MainGameLoop) bounceBallOnEdge(edge int) {
	bounceBack := m.ballEdgeLimit[edge] - m.ballPos[axisY]
	m.ballPos[axisY] = m.ballEdgeLimit[edge] + bounceBack
	m.ballDelta[axisY] *= -1.0
}

func (m *MainGameLoop) maybeSpeedUpBall() {
	timeSinceLast := m.frameTimestamp - m.lastSpeedupTime
	if timeSinceLast >= ballSpeedupEveryNSecs {
		m.ballDelta[axisX] *= ballSpeedupFactor
		m.ballDelta[axisY] *= ballSpeedupFactor
		m.lastSpeedupTime = m.frameTimestamp
	}
}
```

- [ ] **Step 2: Verify it compiles**

Run: `cd go && go build ./...`
Expected: Success

- [ ] **Step 3: Commit**

```bash
git add go/gameloop_main.go
git commit -m "Add MainGameLoop with pong gameplay"
```

---

### Task 11: JoystickTestLoop and MakerFaireLoop

**Files:**
- Create: `go/gameloop_joystick.go`
- Create: `go/gameloop_makerfaire.go`

- [ ] **Step 1: Create gameloop_joystick.go**

```go
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
```

- [ ] **Step 2: Create gameloop_makerfaire.go**

```go
package main

import "time"

type MakerFaireLoop struct {
	fb          *FrameBuffer
	frames      [][]int
	previousTime time.Time
	currentIndex int
}

func NewMakerFaireLoop(fb *FrameBuffer, loader *BitmapLoader) *MakerFaireLoop {
	m := &MakerFaireLoop{fb: fb}
	for _, name := range []string{"trondheim", "maker", "faire"} {
		bm, err := loader.LoadBitmap(name)
		if err != nil {
			panic("failed to load " + name + ": " + err.Error())
		}
		m.frames = append(m.frames, bm.Pixels())
	}
	return m
}

func (m *MakerFaireLoop) OnEnter() {
	m.currentIndex = 0
	m.fb.SetBackgroundFrame(m.frames[0])
	m.previousTime = time.Now()
}

func (m *MakerFaireLoop) OnFrameUpdate() {
	now := time.Now()
	if now.Unix() > m.previousTime.Unix() {
		m.currentIndex = (m.currentIndex + 1) % len(m.frames)
		m.fb.SetBackgroundFrame(m.frames[m.currentIndex])
	}
	m.previousTime = now
}

func (m *MakerFaireLoop) OnEvent(event InputEvent) {
	// No input handling
}

func (m *MakerFaireLoop) NextLoop() string {
	return ""
}
```

- [ ] **Step 3: Verify it compiles**

Run: `cd go && go build ./...`
Expected: Success

- [ ] **Step 4: Commit**

```bash
git add go/gameloop_joystick.go go/gameloop_makerfaire.go
git commit -m "Add JoystickTestLoop and MakerFaireLoop"
```

---

### Task 12: WebSocket Server and Connection Manager

**Files:**
- Create: `go/server.go`

- [ ] **Step 1: Create server.go**

```go
package main

import (
	"context"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"sync"
	"time"

	"nhooyr.io/websocket"
)

type PlayerConnection struct {
	conn    *websocket.Conn
	encoder *JsonFrameEncoder
	input   bool
	output  bool
}

type Server struct {
	mu          sync.RWMutex
	connections map[*websocket.Conn]*PlayerConnection
	events      chan InputEvent
	gameLoop    GameLoop
	fb          *FrameBuffer
	config      Config
	loader      *BitmapLoader
	font        *Font
	gameLoops   map[string]func() GameLoop
}

type clientMessage struct {
	Event   *clientEvent `json:"event,omitempty"`
	Input   *bool        `json:"input,omitempty"`
	Output  *bool        `json:"output,omitempty"`
	Command string       `json:"command,omitempty"`
}

type clientEvent struct {
	Device    int `json:"device"`
	EventType int `json:"eventType"`
	Value     int `json:"value"`
}

func NewServer(cfg Config, fb *FrameBuffer, loader *BitmapLoader, font *Font) *Server {
	s := &Server{
		connections: make(map[*websocket.Conn]*PlayerConnection),
		events:      make(chan InputEvent, 64),
		fb:          fb,
		config:      cfg,
		loader:      loader,
		font:        font,
	}
	s.gameLoops = map[string]func() GameLoop{
		"testimage":  func() GameLoop { return NewTestImageScreen(fb, loader, font) },
		"pressstart": func() GameLoop { return NewPressStartToPlayLoop(fb, loader, font) },
		"maingame":   func() GameLoop { return NewMainGameLoop(fb, loader, font) },
		"joystick":   func() GameLoop { return NewJoystickTestLoop(fb, loader, font) },
		"makerfaire": func() GameLoop { return NewMakerFaireLoop(fb, loader) },
	}
	s.switchToGameLoop("testimage")
	return s
}

func (s *Server) switchToGameLoop(name string) {
	factory, ok := s.gameLoops[name]
	if !ok {
		log.Printf("unknown game loop: %s", name)
		return
	}
	s.gameLoop = factory()
	s.gameLoop.OnEnter()
}

func (s *Server) HandleWebSocket(w http.ResponseWriter, r *http.Request) {
	conn, err := websocket.Accept(w, r, &websocket.AcceptOptions{
		InsecureSkipVerify: true,
	})
	if err != nil {
		log.Printf("websocket accept error: %v", err)
		return
	}
	defer conn.CloseNow()

	pc := &PlayerConnection{
		conn:    conn,
		encoder: NewJsonFrameEncoder(s.config.Width, s.config.Height),
	}

	s.mu.Lock()
	s.connections[conn] = pc
	s.mu.Unlock()

	// Send frameInfo to the new connection
	info := EncodeFrameInfo(s.config.Width, s.config.Height)
	conn.Write(context.Background(), websocket.MessageText, info)

	log.Printf("Client connected (%d total)", len(s.connections))

	defer func() {
		s.mu.Lock()
		delete(s.connections, conn)
		s.mu.Unlock()
		log.Println("Disconnected")
	}()

	// Read loop
	for {
		_, data, err := conn.Read(context.Background())
		if err != nil {
			return
		}
		s.handleMessage(conn, data)
	}
}

func (s *Server) handleMessage(conn *websocket.Conn, data []byte) {
	var msg clientMessage
	if err := json.Unmarshal(data, &msg); err != nil {
		log.Printf("invalid message: %v", err)
		return
	}

	s.mu.RLock()
	pc := s.connections[conn]
	s.mu.RUnlock()
	if pc == nil {
		return
	}

	if msg.Input != nil {
		pc.input = *msg.Input
	}
	if msg.Output != nil {
		pc.output = *msg.Output
	}
	if msg.Event != nil {
		s.events <- InputEvent{
			Device:    msg.Event.Device,
			EventType: msg.Event.EventType,
			Value:     msg.Event.Value,
		}
	}
	if msg.Command == "restart" {
		log.Println("Restart requested by client")
		s.switchToGameLoop("testimage")
	}

	log.Printf("incoming message: %s", string(data))
}

func (s *Server) Run() {
	ticker := time.NewTicker(time.Duration(float64(time.Second) / s.config.FPS))
	defer ticker.Stop()

	for range ticker.C {
		// Process all pending events
		for {
			select {
			case event := <-s.events:
				s.gameLoop.OnEvent(event)
			default:
				goto doneEvents
			}
		}
	doneEvents:

		// Check for game loop transition
		if sr, ok := s.gameLoop.(SwitchRequester); ok {
			if next := sr.NextLoop(); next != "" {
				s.switchToGameLoop(next)
			}
		}

		// Update game state
		s.gameLoop.OnFrameUpdate()

		// Get frame and broadcast
		frame := s.fb.GetAndSwitchFrame()

		s.mu.RLock()
		var encoded []byte
		for _, pc := range s.connections {
			if !pc.output {
				continue
			}
			if encoded == nil {
				encoded = pc.encoder.EncodeFrame(frame)
			}
			if encoded != nil {
				pc.conn.Write(context.Background(), websocket.MessageText, encoded)
			}
		}
		s.mu.RUnlock()
	}
}
```

Note: The `InsecureSkipVerify` field accepts WebSocket connections from any origin. At implementation time, verify the exact field name from the nhooyr.io/websocket docs — it may be `OriginPatterns` in newer versions.

- [ ] **Step 2: Verify it compiles (fix any API naming issues)**

Run: `cd go && go build ./...`
Expected: Success (may need to adjust websocket.AcceptOptions field names)

- [ ] **Step 3: Commit**

```bash
git add go/server.go
git commit -m "Add WebSocket server and connection manager"
```

---

### Task 13: Main entry point with HTTP server

**Files:**
- Modify: `go/main.go`

- [ ] **Step 1: Update main.go with full HTTP server**

```go
package main

import (
	"fmt"
	"io/fs"
	"log"
	"net/http"
	"strings"
)

func main() {
	cfg := ParseConfig()

	fb := NewFrameBuffer(cfg.Width, cfg.Height)
	loader := NewBitmapLoader([]string{
		fmt.Sprintf("res/bitmaps/%dx%d", cfg.Width, cfg.Height),
		"res/sprites",
	})
	font, err := LoadFont("5x7", "res/fonts")
	if err != nil {
		log.Fatalf("failed to load font: %v", err)
	}

	server := NewServer(cfg, fb, loader, font)

	// Serve static files from embedded res/htdocs/
	htdocs, err := fs.Sub(resources, "res/htdocs")
	if err != nil {
		log.Fatalf("failed to get htdocs: %v", err)
	}
	fileServer := http.FileServer(http.FS(htdocs))

	http.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
		// Check for WebSocket upgrade
		if strings.EqualFold(r.Header.Get("Upgrade"), "websocket") {
			server.HandleWebSocket(w, r)
			return
		}
		fileServer.ServeHTTP(w, r)
	})

	// Run game loop in background
	go server.Run()

	addr := fmt.Sprintf("%s:%d", cfg.BindAddr, cfg.Port)
	log.Printf("Listening to port %d", cfg.Port)
	if err := http.ListenAndServe(addr, nil); err != nil {
		log.Fatalf("server error: %v", err)
	}
}
```

- [ ] **Step 2: Build and run smoke test**

Run: `cd go && go build -o pixelpong . && ./pixelpong &`
Expected: Prints "Listening to port 4432"

Run: `curl -s http://localhost:4432/ | head -5`
Expected: Shows beginning of index.html

Run: `kill %1`

- [ ] **Step 3: Commit**

```bash
git add go/main.go
git commit -m "Add main entry point with HTTP/WebSocket server"
```

---

### Task 14: Dockerfile

**Files:**
- Create: `go/Dockerfile`

- [ ] **Step 1: Create Dockerfile**

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

- [ ] **Step 2: Commit**

```bash
git add go/Dockerfile
git commit -m "Add multi-stage Dockerfile for Go build"
```

---

### Task 15: Fix encoder to share encoding across connections

The current server.go has a bug: it only encodes the frame once using the first connection's encoder, but each connection needs its own encoder for delta tracking. Fix this so all connections sharing the same encoder type get the same encoded bytes, but each connection's encoder tracks its own previous frame.

**Files:**
- Modify: `go/server.go`

- [ ] **Step 1: Fix the broadcast logic in Server.Run()**

In `server.go`, replace the broadcast section of `Run()`:

```go
		// Get frame and broadcast
		frame := s.fb.GetAndSwitchFrame()

		s.mu.RLock()
		for _, pc := range s.connections {
			if !pc.output {
				continue
			}
			encoded := pc.encoder.EncodeFrame(frame)
			if encoded != nil {
				pc.conn.Write(context.Background(), websocket.MessageText, encoded)
			}
		}
		s.mu.RUnlock()
```

This gives each connection its own delta tracking, matching the PHP behavior where each connection has its own encoder instance.

- [ ] **Step 2: Verify it compiles**

Run: `cd go && go build ./...`
Expected: Success

- [ ] **Step 3: Commit**

```bash
git add go/server.go
git commit -m "Fix frame encoding to use per-connection encoders"
```

---

### Task 16: Run all tests and integration smoke test

**Files:** (none, verification only)

- [ ] **Step 1: Run all unit tests**

Run: `cd go && go test -v ./...`
Expected: All tests PASS

- [ ] **Step 2: Build and run the server**

Run: `cd go && go build -o pixelpong .`
Expected: Successful build

- [ ] **Step 3: Manual smoke test**

Run: `cd go && ./pixelpong &`
Expected: "Listening to port 4432"

Open `http://localhost:4432/` in browser.
Expected: Canvas appears, test image displays, keyboard controls work.

Run: `kill %1`

- [ ] **Step 4: Run go vet**

Run: `cd go && go vet ./...`
Expected: No issues

- [ ] **Step 5: Final commit if any fixes were needed**

```bash
git add -A go/
git commit -m "Fix issues found during integration testing"
```
