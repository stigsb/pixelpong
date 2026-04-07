package main

import "fmt"

func main() {
	cfg := ParseConfig()
	fmt.Printf("Listening to port %d\n", cfg.Port)
}
