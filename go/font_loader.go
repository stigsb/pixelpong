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
