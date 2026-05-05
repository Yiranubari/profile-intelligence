<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class LoggerMiddleware implements MiddlewareInterface
{
    public function __construct(private LoggerInterface $logger) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $start = microtime(true);

        $response = $handler->handle($request);

        $duration = round((microtime(true) - $start) * 1000, 2);

        $this->logger->info('Request completed', [
            'method'   => $request->getMethod(),
            'path'     => $request->getUri()->getPath(),
            'status'   => $response->getStatusCode(),
            'duration' => $duration . 'ms',
        ]);

        return $response->withHeader('X-Server-Time', $duration . 'ms');
    }
}
