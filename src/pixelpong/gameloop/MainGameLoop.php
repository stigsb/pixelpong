<?php


namespace stigsb\pixelpong\gameloop;


use Interop\Container\ContainerInterface;
use stigsb\pixelpong\frame\FrameBuffer;
use stigsb\pixelpong\gameloop\BaseGameLoop;
use stigsb\pixelpong\server\Event;
use stigsb\pixelpong\bitmap\Sprite;

class MainGameLoop extends BaseGameLoop
{
    /** base speed in pixels per second */
    const BALL_SPEED = 3.0;

    /** pixels per second */
    const PADDLE_SPEED = 10.0;

    /** index in various arrays for the left paddle */
    const LEFT = 0;
    /** index in various arrays for the right paddle */
    const RIGHT = 1;

    const TOP = 0;

    const BOTTOM = 1;

    /** index in various arrays for the ball X coordinate */
    const X = 0;

    /** index in various arrays for the ball Y coordinate */
    const Y = 1;

    /** still initializing the game (measuring frame rate) */
    const GAMESTATE_INITIALIZING = 1;

    /** waiting for one of the players to start the game */
    const GAMESTATE_WAITING = 2;

    /** game in progress */
    const GAMESTATE_PLAYING = 3;

    /** the game (round) is over, and we have a winner! */
    const GAMESTATE_GAMEOVER = 4;

    const PADDLE_CENTER_Y = 12.0;

    const PADDLE_DISTANCE_TO_SIDES = 1.0;

    const BALL_SPEEDUP_EVERY_N_SECS = 10;

    const BALL_SPEEDUP_FACTOR = 1.10;

    /** Influence factor for paddle movement on ball angle (fraction of X speed added to Y) */
    const PADDLE_INFLUENCE = 0.5;

    /** Maximum Y/X ratio to prevent near-vertical trajectories */
    const PADDLE_MAX_ANGLE_RATIO = 1.5;

    const FRAME_EDGE_SIZE = 1.0;

    /** @var int */
    private $displayWidth;

    /** @var int */
    private $displayHeight;

    /** @var Sprite[] */
    private $paddles;

    /** @var Sprite */
    private $ball;

    /** @var double[] */
    private $paddlePositions;

    /** @var double[] */
    private $lastYAxisUpdateTime;

    /** @var int[] */
    private $currentYAxis;

    /** @var int[] */
    private static $paddlePosX = [
        self::LEFT => 1.0,
        self::RIGHT => 45.0,
    ];

    /** @var double[] */
    private $ballDelta;

    /** @var double[] */
    private $ballPos;

    /** @var double */
    private $paddleMinY;

    /** @var double */
    private $paddleMaxY;

    /** @var int */
    private $gameState;

    /** @var array[int]int  map from device id to paddle id */
    private static $inputDevices = [
        Event::DEVICE_JOY_1 => self::LEFT,
        Event::DEVICE_JOY_2 => self::RIGHT,
    ];

    /** @var int|null */
    private $winningSide;

    /** @var array */
    private $ballPaddleLimitX;

    private $ballEdgeLimitY;

    private $paddleHeight;

    private $ballHeight;

    private $ballWidth;

    private $paddleWidth;

    private $initializationTimestamp;

    private $startTimestamp;

    private $approxFrameTime;

    private $frameTimestamp;

    private $lastSpeedupTimestamp;

    public function __construct(FrameBuffer $frameBuffer, ContainerInterface $container)
    {
        parent::__construct($frameBuffer, $container);
        $this->initializeGame($frameBuffer);
    }

    protected function initializeGame(FrameBuffer $frameBuffer)
    {
        $this->background = $this->bitmapLoader->loadBitmap('main_game');
        $this->displayHeight = $frameBuffer->getHeight();
        $this->displayWidth = $frameBuffer->getWidth();
        $this->paddles = [
            self::LEFT  => $this->bitmapLoader->loadSprite('paddle'),
            self::RIGHT => $this->bitmapLoader->loadSprite('paddle'),
        ];
        $this->addSprite($this->paddles[self::LEFT]);
        $this->addSprite($this->paddles[self::RIGHT]);
        $this->paddleHeight = $this->paddles[self::LEFT]->getBitmap()->getHeight();
        $this->paddleWidth = $this->paddles[self::LEFT]->getBitmap()->getWidth();
        $this->paddleMinY = self::FRAME_EDGE_SIZE;
        $this->paddleMaxY = $this->displayHeight - $this->paddleHeight - self::FRAME_EDGE_SIZE;
        $this->ball = $this->bitmapLoader->loadSprite('ball');
        $this->addSprite($this->ball);
        $this->ballHeight = $this->ball->getBitmap()->getHeight();
        $this->ballWidth = $this->ball->getBitmap()->getWidth();
        self::$paddlePosX[self::RIGHT] = $this->displayWidth - 1.0 - $this->paddleWidth;
        $this->ballPaddleLimitX = [
            self::LEFT => (double)(self::$paddlePosX[self::LEFT] + $this->paddleWidth),
            self::RIGHT => (double)(self::$paddlePosX[self::RIGHT] - $this->paddleWidth),
        ];
        $this->ballEdgeLimitY = [
            self::TOP => 1.0,
            self::BOTTOM => (double)($this->displayHeight - $this->ballHeight),
        ];
        $this->frameTimestamp = microtime(true);
        $this->gameState = self::GAMESTATE_INITIALIZING;
    }

    protected function resetGame()
    {
        $this->lastYAxisUpdateTime = [
            self::LEFT  => 0.0,
            self::RIGHT => 0.0,
        ];
        $this->currentYAxis = [
            self::LEFT => Event::AXIS_NEUTRAL,
            self::RIGHT => Event::AXIS_NEUTRAL,
        ];
        $this->winningSide = null;
        $paddle_middle_y = ($this->displayHeight / 2.0) - ($this->paddleHeight / 2.0);
        $this->paddlePositions = [
            self::LEFT  => $paddle_middle_y,
            self::RIGHT => $paddle_middle_y,
        ];
        $this->ballPos = [
            self::X => $this->ballPaddleLimitX[self::LEFT],
            self::Y => self::PADDLE_CENTER_Y,
        ];
        $this->ballDelta = [
            self::X => 0.0,
            self::Y => 0.0,
        ];
        $this->gameState = self::GAMESTATE_INITIALIZING;
        $this->initializationTimestamp = null;
        $this->startTimestamp = null;
        $this->updateBallSpritePosition();
        $this->updatePaddleSpritePositions();
    }

    /**
     * This method is called when the game enters this game loop.
     * This is where you would replace the background frame among other things.
     */
    public function onEnter()
    {
        parent::onEnter();
        $this->resetGame();
    }

    /**
     * An input event occurs (joystick action).
     * @param Event $event
     */
    public function onEvent(Event $event)
    {
        switch ($this->gameState) {
            case self::GAMESTATE_WAITING:
                if ($event->eventType == Event::JOY_BUTTON_1 && $event->value == Event::BUTTON_NEUTRAL) {
                    $this->startGame();
                }
                break;
            case self::GAMESTATE_PLAYING:
                if ($event->eventType == Event::JOY_AXIS_Y) {
                    if ($event->value == Event::AXIS_NEUTRAL) {
                        $this->updatePaddlePositionForDevice($event->device);
                    }
                    $paddle = self::$inputDevices[$event->device];
                    $this->currentYAxis[$paddle] = $event->value;
                }
                break;
            case self::GAMESTATE_GAMEOVER:
                if ($event->eventType == Event::JOY_BUTTON_1 && $event->value == Event::BUTTON_NEUTRAL) {
                    $this->resetGame();
                }
                break;
            default:
                break;
        }
    }

    public function onFrameUpdate()
    {
        $this->frameTimestamp = microtime(true);
        switch ($this->gameState) {
            case self::GAMESTATE_INITIALIZING:
                if (empty($this->initializationTimestamp)) {
                    $this->initializationTimestamp = $this->getCurrentMicrotime();
                } else {
                    $this->approxFrameTime = $this->getCurrentMicrotime() - $this->initializationTimestamp;
                    printf("approxFrameTime: %f\n", $this->approxFrameTime);
                    $this->gameState = self::GAMESTATE_WAITING;
                }
                break;
            case self::GAMESTATE_PLAYING:
                // Move sprites before calling parent
                foreach (self::$inputDevices as $device => $paddle) {
                    $this->updatePaddlePositionForDevice($device);
                }
                $this->updatePaddleSpritePositions();
                $this->updateBallPosition();
                break;
            case self::GAMESTATE_WAITING:
            case self::GAMESTATE_GAMEOVER:
            default:
                break;
        }
        parent::onFrameUpdate();
    }

    protected function startGame()
    {
        $this->ballDelta = [
            self::X => self::PADDLE_SPEED * $this->approxFrameTime,
            self::Y => self::PADDLE_SPEED * $this->approxFrameTime,
        ];
        $this->gameState = self::GAMESTATE_PLAYING;
        $this->lastSpeedupTimestamp = $this->startTimestamp = $this->getCurrentMicrotime();
        printf("Starting game!\n");
    }

    protected function updateBallPosition() {
        $this->ballPos[self::X] += $this->ballDelta[self::X];
        $this->ballPos[self::Y] += $this->ballDelta[self::Y];
        if ($this->ballHasHitTopEdge()) {
            $this->bounceBallOnEdge(self::TOP);
        } elseif ($this->ballHasHitBottomEdge()) {
            $this->bounceBallOnEdge(self::BOTTOM);
        }
        if ($this->ballIsPastLeftPaddle()) {
            if ($this->ballHitPaddle(self::LEFT)) {
                $this->bounceBallOnPaddle(self::LEFT);
                print "bounce ball on left paddle\n";
            } else {
                $this->playerWon(self::RIGHT);
            }
        } elseif ($this->ballIsPastRightPaddle()) {
            printf("past right paddle!\n");
            if ($this->ballHitPaddle(self::RIGHT)) {
                $this->bounceBallOnPaddle(self::RIGHT);
                print "bounce ball on right paddle\n";
            } else {
                $this->playerWon(self::LEFT);
            }
        }
        printf("new ball position: [%d,%d]\n", $this->ballPos[self::X], $this->ballPos[self::Y]);
        $this->updateBallSpritePosition();
    }

    protected function updateBallSpritePosition()
    {
        $this->ball->moveTo((int)$this->ballPos[self::X], (int)$this->ballPos[self::Y]);
    }

    protected function updatePaddleSpritePositions()
    {
        foreach ($this->paddlePositions as $paddle => $ypos) {
            $this->paddles[$paddle]->moveTo(self::$paddlePosX[$paddle], (int)$ypos);
        }
    }

    protected function updatePaddlePositionForDevice($device)
    {
        $paddle = self::$inputDevices[$device];
        $now_us = $this->getCurrentMicrotime();
        $elapsed = $now_us - $this->lastYAxisUpdateTime[$paddle];
        $new_pos = $this->paddlePositions[$paddle] + ((double)self::PADDLE_SPEED * $elapsed * $this->currentYAxis[$paddle]);
        if ($new_pos < $this->paddleMinY) {
            $new_pos = $this->paddleMinY;
        } elseif ($new_pos > $this->paddleMaxY) {
            $new_pos = $this->paddleMaxY;
        }
        $this->paddlePositions[$paddle] = $new_pos;
        $this->lastYAxisUpdateTime[$paddle] = $now_us;
//        printf("updating position for device %d to %.3f (elapsed %.6f, axis %d)\n", $device, $new_pos, $elapsed, $this->currentYAxis[$paddle]);
    }

    protected function playerWon($side)
    {
        printf("%s side won!\n", $side == self::LEFT ? 'Left' : 'Right');
        $this->gameState = self::GAMESTATE_GAMEOVER;
        $this->winningSide = $side;
    }

    /**
     * @return bool
     */
    protected function ballHasHitTopEdge()
    {
        return ($this->ballPos[self::Y] <= $this->ballEdgeLimitY[self::TOP]);
    }

    /**
     * @return bool
     */
    protected function ballHasHitBottomEdge()
    {
        return ($this->ballPos[self::Y] >= $this->ballEdgeLimitY[self::BOTTOM]);
    }

    /**
     * @return bool
     */
    protected function ballIsPastLeftPaddle()
    {
        return ($this->ballPos[self::X] <= $this->ballPaddleLimitX[self::LEFT]);
    }

    /**
     * @return bool
     */
    protected function ballIsPastRightPaddle()
    {
        return ($this->ballPos[self::X] >= $this->ballPaddleLimitX[self::RIGHT]);
    }

    /**
     * @param int $paddle
     * @return bool
     */
    protected function ballHitPaddle($paddle)
    {
        $ball_y = $this->ballPos[self::Y];
        $paddle_y_min = $this->paddlePositions[$paddle] - $this->ballHeight;
        $paddle_y_max = $this->paddlePositions[$paddle] + $this->paddleHeight + $this->ballHeight;
        return ($ball_y > $paddle_y_min && $ball_y < $paddle_y_max);
    }

    /**
     * @param int $paddle  self::LEFT or self::RIGHT
     */
    protected function bounceBallOnPaddle($paddle)
    {
        $bounceBack = $this->ballPaddleLimitX[$paddle] - $this->ballPos[self::X];
        $this->ballPos[self::X] = $this->ballPaddleLimitX[$paddle] + $bounceBack;
        $this->ballDelta[self::X] *= -1.0;

        // Paddle movement influences ball angle, like classic Pong
        $paddleDirection = $this->currentYAxis[$paddle]; // -1, 0, or 1
        if ($paddleDirection != Event::AXIS_NEUTRAL) {
            $influence = abs($this->ballDelta[self::X]) * self::PADDLE_INFLUENCE;
            $this->ballDelta[self::Y] += $influence * $paddleDirection;

            // Cap Y speed to prevent near-vertical trajectories
            $maxY = abs($this->ballDelta[self::X]) * self::PADDLE_MAX_ANGLE_RATIO;
            $sign = $this->ballDelta[self::Y] >= 0 ? 1.0 : -1.0;
            $absY = abs($this->ballDelta[self::Y]);
            if ($absY > $maxY) {
                $this->ballDelta[self::Y] = $sign * $maxY;
            }
        }

        $this->maybeSpeedUpBall();
    }

    /**
     * @param int $edge  self::TOP or self::BOTTOM
     */
    protected function bounceBallOnEdge($edge)
    {
        $bounceBack = $this->ballEdgeLimitY[$edge] - $this->ballPos[self::Y];
        $this->ballPos[self::Y] = $this->ballEdgeLimitY[$edge] + $bounceBack;
        $this->ballDelta[self::Y] *= -1.0;
    }

    protected function getCurrentMicrotime()
    {
        return $this->frameTimestamp;
    }

    protected function maybeSpeedUpBall()
    {
        $currentMicrotime = $this->getCurrentMicrotime();
        $timeSinceLast = $currentMicrotime - $this->lastSpeedupTimestamp;
        printf("timeSinceLast=%f\n", $timeSinceLast);
        if ($timeSinceLast >= self::BALL_SPEEDUP_EVERY_N_SECS) {
            $this->ballDelta[self::X] *= self::BALL_SPEEDUP_FACTOR;
            $this->ballDelta[self::Y] *= self::BALL_SPEEDUP_FACTOR;
            $this->lastSpeedupTimestamp = $currentMicrotime;
            printf("Speeding up ball!\n");
        }
    }
}
