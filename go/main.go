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
