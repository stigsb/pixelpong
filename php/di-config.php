<?php

use Ratchet\Http\HttpServerInterface;
use React\Socket\ServerInterface;
use stigsb\pixelpong\bitmap\BitmapLoader;
use stigsb\pixelpong\bitmap\FontLoader;
use stigsb\pixelpong\frame\FrameBuffer;
use stigsb\pixelpong\frame\OffscreenFrameBuffer;

$__topdir = dirname(__DIR__);
$__w = 47;
$__h = 27;

return [
    'framebuffer.width'                 => $__w,
    'framebuffer.height'                => $__h,
    'server.port'                       => DI\env('PONG_PORT', '4432'),
    'server.bind_addr'                  => DI\env('PONG_BIND_ADDR', '0.0.0.0'),
    'server.fps'                        => DI\env('PONG_FPS', '10.0'),
    FrameBuffer::class                  => DI\create(OffscreenFrameBuffer::class)
        ->constructor(
            DI\get('framebuffer.width'),
            DI\get('framebuffer.height')
        ),
    ServerInterface::class              => DI\create(React\Socket\Server::class),
    HttpServerInterface::class          => DI\create(Ratchet\WebSocket\WsServer::class),
    FontLoader::class                   => DI\create(FontLoader::class)
        ->constructor("{$__topdir}/res/fonts"),
    BitmapLoader::class                 => DI\create(BitmapLoader::class)
        ->constructor("{$__topdir}/res/bitmaps/{$__w}x{$__h}:{$__topdir}/res/sprites"),
];
