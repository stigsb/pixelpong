package main

import (
	"context"
	"encoding/json"
	"log"
	"net/http"
	"sync"
	"sync/atomic"
	"time"

	"nhooyr.io/websocket"
)

type PlayerConnection struct {
	conn    *websocket.Conn
	encoder *JsonFrameEncoder
	input   atomic.Bool
	output  atomic.Bool
}

type Server struct {
	mu          sync.RWMutex
	connections map[*websocket.Conn]*PlayerConnection
	events      chan InputEvent
	commands    chan string // restart commands routed to ticker goroutine
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
		commands:    make(chan string, 4),
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
		pc.input.Store(*msg.Input)
	}
	if msg.Output != nil {
		pc.output.Store(*msg.Output)
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
		s.commands <- "restart"
	}
}

func (s *Server) Run() {
	ticker := time.NewTicker(time.Duration(float64(time.Second) / s.config.FPS))
	defer ticker.Stop()

	for range ticker.C {
		// Process all pending commands and events
		for {
			select {
			case cmd := <-s.commands:
				if cmd == "restart" {
					s.switchToGameLoop("testimage")
				}
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

		// Get frame and broadcast to each connection with its own encoder
		frame := s.fb.GetAndSwitchFrame()

		s.mu.RLock()
		for _, pc := range s.connections {
			if !pc.output.Load() {
				continue
			}
			encoded := pc.encoder.EncodeFrame(frame)
			if encoded != nil {
				pc.conn.Write(context.Background(), websocket.MessageText, encoded)
			}
		}
		s.mu.RUnlock()
	}
}
