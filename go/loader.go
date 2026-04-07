package main

import (
	"embed"
	"fmt"
	"strings"
)

//go:embed all:res
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
