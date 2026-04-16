<?php

namespace stigsb\pixelpong\server;

use Psr\Http\Message\ServerRequestInterface;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use React\EventLoop\Loop;
use React\Http\Message\Response;
use React\Stream\CompositeStream;
use React\Stream\ThroughStream;

class WebSocketHandler
{
    private GameServer $gameServer;
    private ServerNegotiator $negotiator;

    public function __construct(GameServer $gameServer)
    {
        $this->gameServer = $gameServer;
        $this->negotiator = new ServerNegotiator(new RequestVerifier());
    }

    public function handleUpgrade(ServerRequestInterface $request): Response
    {
        $response = $this->negotiator->handshake($request);

        if ($response->getStatusCode() !== 101) {
            return new Response(
                $response->getStatusCode(),
                ['Content-Type' => 'text/plain'],
                'WebSocket handshake failed'
            );
        }

        $in = new ThroughStream();
        $out = new ThroughStream();
        $conn = new WebSocketConnection($out);

        $messageBuffer = new MessageBuffer(
            new CloseFrameChecker(),
            function ($message) use ($conn) {
                try {
                    $this->gameServer->onMessage($conn, (string)$message);
                } catch (\Throwable $e) {
                    $this->gameServer->onError($conn, $e);
                }
            },
            function ($frame) use ($conn, $out) {
                $opcode = $frame->getOpcode();
                if ($opcode === Frame::OP_PING) {
                    $pong = new Frame($frame->getPayload(), true, Frame::OP_PONG);
                    $out->write($pong->getContents());
                } elseif ($opcode === Frame::OP_CLOSE) {
                    $conn->close();
                }
            },
            true // expect masked frames from client
        );

        $in->on('data', function (string $data) use ($messageBuffer) {
            $messageBuffer->onData($data);
        });

        $in->on('close', function () use ($conn) {
            $this->gameServer->onClose($conn);
        });

        $body = new CompositeStream($out, $in);

        // Defer onOpen to the next tick so react/http has time to set up
        // stream piping before GameServer writes frameInfo to $out
        Loop::futureTick(function () use ($conn) {
            $this->gameServer->onOpen($conn);
        });

        return new Response(
            101,
            [
                'Upgrade' => 'websocket',
                'Connection' => 'Upgrade',
                'Sec-WebSocket-Accept' => $response->getHeaderLine('Sec-WebSocket-Accept'),
            ],
            $body
        );
    }
}
