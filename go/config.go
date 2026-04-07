package main

import (
	"flag"
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
