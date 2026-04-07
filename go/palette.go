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
