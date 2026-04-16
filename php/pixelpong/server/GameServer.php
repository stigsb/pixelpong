<?php

namespace stigsb\pixelpong\server;

use Psr\Container\ContainerInterface;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use React\EventLoop\LoopInterface;
use stigsb\pixelpong\frame\FrameBuffer;
use stigsb\pixelpong\frame\JsonFrameEncoder;
use stigsb\pixelpong\gameloop\GameLoop;
use stigsb\pixelpong\gameloop\TestImageScreen;

class GameServer implements MessageComponentInterface
{
    /** @var LoopInterface */
    private $loop;

    /** @var GameLoop */
    private $gameLoop;

    /** @var \SplObjectStorage */
    private $connections;

    /** @var FrameBuffer */
    private $frameBuffer;

    /** @var ContainerInterface */
    private $container;

    /** @var \React\EventLoop\TimerInterface */
    private $updateTimer;

    public function __construct(LoopInterface $loop, FrameBuffer $frameBuffer, ContainerInterface $container)
    {
        $this->loop = $loop;
        $this->frameBuffer = $frameBuffer;
        $this->container = $container;
        $this->connections = new \SplObjectStorage();
        $fps = (float)$container->get('server.fps');
        $this->updateTimer = $this->loop->addPeriodicTimer(1.0/$fps, [$this, 'onFrameUpdate']);
        $this->switchToGameLoop($container->get(TestImageScreen::class));
    }

    /**
     * When a new connection is opened it will be passed to this method
     * @param  ConnectionInterface $conn The socket/connection that just connected to your application
     * @throws \Exception
     */
    public function onOpen(ConnectionInterface $conn)
    {
        $frameEncoder = new JsonFrameEncoder($this->frameBuffer);
//        $frameEncoder = new AsciiFrameEncoder($this->frameBuffer);
        $playerConnection = new ActivePlayerConnection($frameEncoder);
        $this->connections->attach($conn, $playerConnection);
        foreach ($this->connections as $conn) {
            /** @var PlayerConnection $playerConnection */
            $playerConnection = $this->connections[$conn];
            print "conn: "; var_dump(get_class($conn));
            print "playerConnection: "; var_dump(get_class($playerConnection));
            $conn->send($playerConnection->getFrameEncoder()->encodeFrameInfo($this->frameBuffer));
        }
    }

    /**
     * This is called before or after a socket is closed (depends on how it's closed).  SendMessage to $conn will not result in an error if it has already been closed.
     * @param  ConnectionInterface $conn The socket/connection that is closing/closed
     * @throws \Exception
     */
    public function onClose(ConnectionInterface $conn)
    {
        printf("Disconnected\n");
        $this->connections->detach($conn);
    }

    /**
     * If there is an error with one of the sockets, or somewhere in the application where an Exception is thrown,
     * the Exception is sent back down the stack, handled by the Server and bubbled back up the application through this method
     * @param  ConnectionInterface $conn
     * @param  \Exception $e
     * @throws \Exception
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        printf("ERROR: %s\n", $e->getMessage());
    }

    /**
     * Triggered when a client sends data through the socket
     * @param  \Ratchet\ConnectionInterface $from The socket/connection that sent the message to your application
     * @param  string $rawmsg The message received
     * @throws \Exception
     */
    public function onMessage(ConnectionInterface $from, $rawmsg)
    {
        $msg = json_decode($rawmsg);
        if ($msg === null) {
            printf("Invalid JSON from client: %s\n", $rawmsg);
            return;
        }
        if (isset($msg->V) && isset($msg->D) && isset($msg->T)) {
            $msg = (object)['event' => (object)['device' => $msg->D, 'eventType' => $msg->T, 'value' => $msg->V]];
            $rawmsg .= json_encode($msg);
        }
        if (isset($msg->input)) {
            $this->connections[$from]->setInputEnabled((bool)$msg->input);
        }
        if (isset($msg->output)) {
            $this->connections[$from]->setOutputEnabled((bool)$msg->output);
        }
        if (isset($msg->event)) {
            $event = new Event($msg->event->device, $msg->event->eventType, $msg->event->value);
            $this->onEvent($event);
        }
        printf("incoming message: $rawmsg\n");
        if (isset($msg->command)) {
            switch ($msg->command) {
                case 'restart':
                    die("Restarting on request from client!\n");
            }
        }
    }

    function onFrameUpdate()
    {
        $utime = microtime(true);
        $time = (int)$utime;
        $ms = (int)(($utime - $time) * 1000);
//        printf("== frameupdate: %s.%03d\n", strftime('%H:%M:%S', $time), $ms);
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

    public function onEvent(Event $event)
    {
        $this->gameLoop->onEvent($event);
    }

    public function switchToGameLoop(GameLoop $gameLoop)
    {
        $this->gameLoop = $gameLoop;
        $gameLoop->onEnter();
    }

}
