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
