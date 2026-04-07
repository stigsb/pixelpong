package main

import (
	"math"
	"time"
)

const (
	ballSpeed             = 3.0
	paddleSpeed           = 10.0
	paddleCenterY         = 12.0
	paddleDistToSides     = 1.0
	ballSpeedupEveryNSecs = 10
	ballSpeedupFactor     = 1.10
	frameEdgeSize         = 1.0
	paddleInfluence       = 0.5 // moving paddle adds/removes half X speed to Y speed
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

	// Paddle movement influences ball angle (like classic Pong)
	paddleDirection := m.currentYAxis[paddle] // -1, 0, or 1
	if paddleDirection != AxisNeutral {
		influence := math.Abs(m.ballDelta[axisX]) * paddleInfluence
		m.ballDelta[axisY] += influence * float64(paddleDirection)

		// Clamp Y speed so the angle stays between ~20 and ~70 degrees
		maxY := math.Abs(m.ballDelta[axisX]) * 1.5
		minY := math.Abs(m.ballDelta[axisX]) * 0.35
		absY := math.Abs(m.ballDelta[axisY])
		sign := 1.0
		if m.ballDelta[axisY] < 0 {
			sign = -1.0
		}
		if absY > maxY {
			absY = maxY
		}
		if absY < minY {
			absY = minY
		}
		m.ballDelta[axisY] = sign * absY
	}

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
