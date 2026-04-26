<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class ApiVersionMiddleware implements MiddlewareInterface
{
    use JsonResponseTrait;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $version = $request->getHeaderLine('X-API-Version');

        if ($version === '' || $version !== '1') {
            return $this->json(new Response(), 400, [
                'status' => 'error',
                'message' => 'API version header required',
            ]);
        }

        return $handler->handle($request);
    }
}
