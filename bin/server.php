#!/usr/bin/env php
<?php

/** @var DI\Container $container */
$container = require dirname(__DIR__) . '/php/bootstrap.php';
$container->set(Psr\Container\ContainerInterface::class, $container);
$options = getopt('f:p:h');
if (isset($options['p'])) {
    $container->set('server.port', (int)$options['p']);
}
if (isset($options['f'])) {
    $container->set('server.fps', (float)$options['f']);
}

$loop = React\EventLoop\Loop::get();
$container->set(React\EventLoop\LoopInterface::class, $loop);

$gameServer = $container->get(stigsb\pixelpong\server\GameServer::class);
$wsHandler = new stigsb\pixelpong\server\WebSocketHandler($gameServer);
$htdocsPath = dirname(__DIR__) . '/res/htdocs';

$mimeTypes = [
    'html' => 'text/html',
    'css'  => 'text/css',
    'js'   => 'application/javascript',
    'json' => 'application/json',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'gif'  => 'image/gif',
    'svg'  => 'image/svg+xml',
    'ico'  => 'image/x-icon',
];

$httpServer = new React\Http\HttpServer(function (Psr\Http\Message\ServerRequestInterface $request) use ($wsHandler, $htdocsPath, $mimeTypes) {
    if (strtolower($request->getHeaderLine('Upgrade')) === 'websocket') {
        return $wsHandler->handleUpgrade($request);
    }

    $path = $request->getUri()->getPath();
    if ($path === '/') {
        $path = '/index.html';
    }

    $file = $htdocsPath . $path;
    $realFile = realpath($file);

    if ($realFile && str_starts_with($realFile, realpath($htdocsPath)) && is_file($realFile)) {
        $ext = pathinfo($realFile, PATHINFO_EXTENSION);
        $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
        return new React\Http\Message\Response(
            200,
            ['Content-Type' => $mime],
            file_get_contents($realFile)
        );
    }

    return new React\Http\Message\Response(404, ['Content-Type' => 'text/plain'], 'Not Found');
});

$socket = new React\Socket\SocketServer($container->get('server.bind_addr') . ':' . $container->get('server.port'), [], $loop);
$httpServer->listen($socket);

printf("Listening on port %d\n", $container->get('server.port'));

$loop->run();
