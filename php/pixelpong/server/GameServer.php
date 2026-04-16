<?php

namespace stigsb\pixelpong\server;

use Psr\Container\ContainerInterface;
use React\EventLoop\LoopInterface;
use stigsb\pixelpong\frame\FrameBuffer;
use stigsb\pixelpong\frame\JsonFrameEncoder;
use stigsb\pixelpong\gameloop\GameLoop;
use stigsb\pixelpong\gameloop\TestImageScreen;

class GameServer
{
    private LoopInterface $loop;
    private GameLoop $gameLoop;
    private \SplObjectStorage $connections;
    private FrameBuffer $frameBuffer;
    private ContainerInterface $container;
    private \React\EventLoop\TimerInterface $updateTimer;

    public function __construct(LoopInterface $loop, FrameBuffer $frameBuffer, ContainerInterface $container)
    {
        $this->loop = $loop;
        $this->frameBuffer = $frameBuffer;
        $this->container = $container;
        $this->connections = new \SplObjectStorage();
        $fps = (float)$container->get('server.fps');
        $this->updateTimer = $this->loop->addPeriodicTimer(1.0 / $fps, [$this, 'onFrameUpdate']);
        $this->switchToGameLoop($container->get(TestImageScreen::class));
    }

    public function onOpen(WebSocketConnection $conn): void
    {
        $frameEncoder = new JsonFrameEncoder($this->frameBuffer);
        $playerConnection = new ActivePlayerConnection($frameEncoder);
        $this->connections->offsetSet($conn, $playerConnection);
        $conn->send($frameEncoder->encodeFrameInfo($this->frameBuffer));
    }

    public function onClose(WebSocketConnection $conn): void
    {
        $this->connections->offsetUnset($conn);
    }

    public function onError(WebSocketConnection $conn, \Throwable $e): void
    {
        printf("ERROR: %s\n", $e->getMessage());
    }

    public function onMessage(WebSocketConnection $conn, string $rawmsg): void
    {
        $msg = json_decode($rawmsg);
        if ($msg === null) {
            return;
        }
        if (isset($msg->V) && isset($msg->D) && isset($msg->T)) {
            $msg = (object)['event' => (object)['device' => $msg->D, 'eventType' => $msg->T, 'value' => $msg->V]];
        }
        if (isset($msg->input)) {
            $this->connections[$conn]->setInputEnabled((bool)$msg->input);
        }
        if (isset($msg->output)) {
            $this->connections[$conn]->setOutputEnabled((bool)$msg->output);
        }
        if (isset($msg->event)) {
            $event = new Event($msg->event->device, $msg->event->eventType, $msg->event->value);
            $this->onEvent($event);
        }
        if (isset($msg->command)) {
            switch ($msg->command) {
                case 'restart':
                    die("Restarting on request from client!\n");
            }
        }
    }

    public function onFrameUpdate(): void
    {
        $this->gameLoop->onFrameUpdate();
        $frame = $this->frameBuffer->getAndSwitchFrame();
        foreach ($this->connections as $conn) {
            /** @var PlayerConnection $playerConnection */
            $playerConnection = $this->connections[$conn];
            if (!$playerConnection->isOutputEnabled()) {
                continue;
            }
            $encoded = $playerConnection->getFrameEncoder()->encodeFrame($frame);
            if ($encoded) {
                $conn->send($encoded);
            }
        }
    }

    public function onEvent(Event $event): void
    {
        $this->gameLoop->onEvent($event);
    }

    public function switchToGameLoop(GameLoop $gameLoop): void
    {
        $this->gameLoop = $gameLoop;
        $gameLoop->onEnter();
    }
}
