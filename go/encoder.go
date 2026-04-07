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
	// Initialize with -1 so the first frame always triggers a full frame send.
	prev := make([]int, size)
	for i := range prev {
		prev[i] = -1
	}
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
