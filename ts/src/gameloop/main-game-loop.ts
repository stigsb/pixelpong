import type { FrameBuffer } from "../frame/frame-buffer.js";
import { Event, EventType, EventValue, Device } from "../server/event.js";
import { BaseGameLoop } from "./base-game-loop.js";
import type { Container } from "../container.js";

const LEFT = 0;
const RIGHT = 1;
const TOP = 0;
const BOTTOM = 1;
const X = 0;
const Y = 1;

enum GameState {
  INITIALIZING = 1,
  WAITING = 2,
  PLAYING = 3,
  GAMEOVER = 4,
}

const BALL_SPEED = 3.0;
const PADDLE_SPEED = 10.0;
const FRAME_EDGE_SIZE = 1.0;
const BALL_SPEEDUP_EVERY_N_SECS = 10;
const BALL_SPEEDUP_FACTOR = 1.1;
const PADDLE_INFLUENCE = 0.5;
const PADDLE_MAX_ANGLE_RATIO = 1.5;

const inputDevices: Record<number, number> = {
  [Device.JOY_1]: LEFT,
  [Device.JOY_2]: RIGHT,
};

export class MainGameLoop extends BaseGameLoop {
  private displayWidth: number;
  private displayHeight: number;
  private paddles: [typeof this.sprites[0], typeof this.sprites[0]];
  private ball!: typeof this.sprites[0];
  private paddlePositions: [number, number] = [0, 0];
  private lastYAxisUpdateTime: [number, number] = [0, 0];
  private currentYAxis: [number, number] = [0, 0];
  private paddlePosX: [number, number];
  private ballDelta: [number, number] = [0, 0];
  private ballPos: [number, number] = [0, 0];
  private paddleMinY: number;
  private paddleMaxY: number;
  private gameState: GameState = GameState.INITIALIZING;
  private winningSide: number | null = null;
  private ballPaddleLimitX: [number, number];
  private ballEdgeLimitY: [number, number];
  private paddleHeight: number;
  private ballHeight: number;
  private ballWidth: number;
  private paddleWidth: number;
  private initializationTimestamp: number | null = null;
  private startTimestamp: number | null = null;
  private approxFrameTime = 0;
  private frameTimestamp: number;
  private lastSpeedupTimestamp = 0;

  constructor(frameBuffer: FrameBuffer, container: Container) {
    super(frameBuffer, container);

    this.background = this.bitmapLoader.loadBitmap("main_game");
    this.displayHeight = frameBuffer.getHeight();
    this.displayWidth = frameBuffer.getWidth();

    const leftPaddle = this.bitmapLoader.loadSprite("paddle");
    const rightPaddle = this.bitmapLoader.loadSprite("paddle");
    this.paddles = [leftPaddle, rightPaddle];
    this.addSprite(leftPaddle);
    this.addSprite(rightPaddle);

    this.paddleHeight = leftPaddle.getBitmap().getHeight();
    this.paddleWidth = leftPaddle.getBitmap().getWidth();
    this.paddleMinY = FRAME_EDGE_SIZE;
    this.paddleMaxY = this.displayHeight - this.paddleHeight - FRAME_EDGE_SIZE;

    this.ball = this.bitmapLoader.loadSprite("ball");
    this.addSprite(this.ball);
    this.ballHeight = this.ball.getBitmap().getHeight();
    this.ballWidth = this.ball.getBitmap().getWidth();

    this.paddlePosX = [1.0, this.displayWidth - 1.0 - this.paddleWidth];
    this.ballPaddleLimitX = [
      this.paddlePosX[LEFT] + this.paddleWidth,
      this.paddlePosX[RIGHT] - this.paddleWidth,
    ];
    this.ballEdgeLimitY = [
      1.0,
      this.displayHeight - this.ballHeight,
    ];
    this.frameTimestamp = performance.now() / 1000;
  }

  private resetGame(): void {
    this.lastYAxisUpdateTime = [0, 0];
    this.currentYAxis = [EventValue.AXIS_NEUTRAL, EventValue.AXIS_NEUTRAL];
    this.winningSide = null;
    const paddleMiddleY = this.displayHeight / 2.0 - this.paddleHeight / 2.0;
    this.paddlePositions = [paddleMiddleY, paddleMiddleY];
    const paddleCenterY = this.displayHeight / 2.0 - this.ballHeight / 2.0;
    this.ballPos = [this.ballPaddleLimitX[LEFT], paddleCenterY];
    this.ballDelta = [0, 0];
    this.gameState = GameState.INITIALIZING;
    this.initializationTimestamp = null;
    this.startTimestamp = null;
    this.updateBallSpritePosition();
    this.updatePaddleSpritePositions();
  }

  onEnter(): void {
    super.onEnter();
    this.resetGame();
  }

  onEvent(event: Event): void {
    switch (this.gameState) {
      case GameState.WAITING:
        if (event.eventType === EventType.JOY_BUTTON_1 && event.value === EventValue.BUTTON_NEUTRAL) {
          this.startGame();
        }
        break;
      case GameState.PLAYING:
        if (event.eventType === EventType.JOY_AXIS_Y) {
          if (event.value === EventValue.AXIS_NEUTRAL) {
            this.updatePaddlePositionForDevice(event.device);
          }
          const paddle = inputDevices[event.device];
          this.currentYAxis[paddle] = event.value;
        }
        break;
      case GameState.GAMEOVER:
        if (event.eventType === EventType.JOY_BUTTON_1 && event.value === EventValue.BUTTON_NEUTRAL) {
          this.resetGame();
        }
        break;
    }
  }

  onFrameUpdate(): void {
    this.frameTimestamp = performance.now() / 1000;
    switch (this.gameState) {
      case GameState.INITIALIZING:
        if (this.initializationTimestamp === null) {
          this.initializationTimestamp = this.getCurrentTime();
        } else {
          this.approxFrameTime = this.getCurrentTime() - this.initializationTimestamp;
          console.log(`approxFrameTime: ${this.approxFrameTime.toFixed(6)}`);
          this.gameState = GameState.WAITING;
        }
        break;
      case GameState.PLAYING:
        for (const [device] of Object.entries(inputDevices)) {
          this.updatePaddlePositionForDevice(Number(device));
        }
        this.updatePaddleSpritePositions();
        this.updateBallPosition();
        break;
    }
    super.onFrameUpdate();
  }

  private startGame(): void {
    this.ballDelta = [
      PADDLE_SPEED * this.approxFrameTime,
      PADDLE_SPEED * this.approxFrameTime,
    ];
    this.gameState = GameState.PLAYING;
    this.lastSpeedupTimestamp = this.startTimestamp = this.getCurrentTime();
    console.log("Starting game!");
  }

  private updateBallPosition(): void {
    this.ballPos[X] += this.ballDelta[X];
    this.ballPos[Y] += this.ballDelta[Y];
    if (this.ballHasHitTopEdge()) {
      this.bounceBallOnEdge(TOP);
    } else if (this.ballHasHitBottomEdge()) {
      this.bounceBallOnEdge(BOTTOM);
    }
    if (this.ballIsPastLeftPaddle()) {
      if (this.ballHitPaddle(LEFT)) {
        this.bounceBallOnPaddle(LEFT);
        console.log("bounce ball on left paddle");
      } else {
        this.playerWon(RIGHT);
      }
    } else if (this.ballIsPastRightPaddle()) {
      console.log("past right paddle!");
      if (this.ballHitPaddle(RIGHT)) {
        this.bounceBallOnPaddle(RIGHT);
        console.log("bounce ball on right paddle");
      } else {
        this.playerWon(LEFT);
      }
    }
    this.updateBallSpritePosition();
  }

  private updateBallSpritePosition(): void {
    this.ball.moveTo(Math.floor(this.ballPos[X]), Math.floor(this.ballPos[Y]));
  }

  private updatePaddleSpritePositions(): void {
    this.paddles[LEFT].moveTo(this.paddlePosX[LEFT], Math.floor(this.paddlePositions[LEFT]));
    this.paddles[RIGHT].moveTo(this.paddlePosX[RIGHT], Math.floor(this.paddlePositions[RIGHT]));
  }

  private updatePaddlePositionForDevice(device: number): void {
    const paddle = inputDevices[device];
    if (paddle === undefined) return;
    const now = this.getCurrentTime();
    const elapsed = now - this.lastYAxisUpdateTime[paddle];
    let newPos = this.paddlePositions[paddle] + PADDLE_SPEED * elapsed * this.currentYAxis[paddle];
    if (newPos < this.paddleMinY) newPos = this.paddleMinY;
    if (newPos > this.paddleMaxY) newPos = this.paddleMaxY;
    this.paddlePositions[paddle] = newPos;
    this.lastYAxisUpdateTime[paddle] = now;
  }

  private playerWon(side: number): void {
    console.log(`${side === LEFT ? "Left" : "Right"} side won!`);
    this.gameState = GameState.GAMEOVER;
    this.winningSide = side;
  }

  private ballHasHitTopEdge(): boolean {
    return this.ballPos[Y] <= this.ballEdgeLimitY[TOP];
  }

  private ballHasHitBottomEdge(): boolean {
    return this.ballPos[Y] >= this.ballEdgeLimitY[BOTTOM];
  }

  private ballIsPastLeftPaddle(): boolean {
    return this.ballPos[X] <= this.ballPaddleLimitX[LEFT];
  }

  private ballIsPastRightPaddle(): boolean {
    return this.ballPos[X] >= this.ballPaddleLimitX[RIGHT];
  }

  private ballHitPaddle(paddle: number): boolean {
    const ballY = this.ballPos[Y];
    const paddleYMin = this.paddlePositions[paddle] - this.ballHeight;
    const paddleYMax = this.paddlePositions[paddle] + this.paddleHeight + this.ballHeight;
    return ballY > paddleYMin && ballY < paddleYMax;
  }

  private bounceBallOnPaddle(paddle: number): void {
    const bounceBack = this.ballPaddleLimitX[paddle] - this.ballPos[X];
    this.ballPos[X] = this.ballPaddleLimitX[paddle] + bounceBack;
    this.ballDelta[X] *= -1.0;

    // Paddle movement influences ball angle, like classic Pong
    const paddleDirection = this.currentYAxis[paddle]; // -1, 0, or 1
    if (paddleDirection !== EventValue.AXIS_NEUTRAL) {
      const influence = Math.abs(this.ballDelta[X]) * PADDLE_INFLUENCE;
      this.ballDelta[Y] += influence * paddleDirection;

      // Cap Y speed to prevent near-vertical trajectories
      const maxY = Math.abs(this.ballDelta[X]) * PADDLE_MAX_ANGLE_RATIO;
      const absY = Math.abs(this.ballDelta[Y]);
      if (absY > maxY) {
        const sign = this.ballDelta[Y] >= 0 ? 1.0 : -1.0;
        this.ballDelta[Y] = sign * maxY;
      }
    }

    this.maybeSpeedUpBall();
  }

  private bounceBallOnEdge(edge: number): void {
    const bounceBack = this.ballEdgeLimitY[edge] - this.ballPos[Y];
    this.ballPos[Y] = this.ballEdgeLimitY[edge] + bounceBack;
    this.ballDelta[Y] *= -1.0;
  }

  private getCurrentTime(): number {
    return this.frameTimestamp;
  }

  private maybeSpeedUpBall(): void {
    const currentTime = this.getCurrentTime();
    const timeSinceLast = currentTime - this.lastSpeedupTimestamp;
    if (timeSinceLast >= BALL_SPEEDUP_EVERY_N_SECS) {
      this.ballDelta[X] *= BALL_SPEEDUP_FACTOR;
      this.ballDelta[Y] *= BALL_SPEEDUP_FACTOR;
      this.lastSpeedupTimestamp = currentTime;
      console.log("Speeding up ball!");
    }
  }
}
