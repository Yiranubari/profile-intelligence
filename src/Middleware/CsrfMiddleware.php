<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class CsrfMiddleware implements MiddlewareInterface
{
    use JsonResponseTrait;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $handler->handle($request);
        }

        $authorization = trim($request->getHeaderLine('Authorization'));
        if (preg_match('/^Bearer\s+\S+$/i', $authorization) === 1) {
            return $handler->handle($request);
        }

        $cookieToken = $request->getCookieParams()['csrf_token'] ?? null;
        $headerToken = trim($request->getHeaderLine('X-CSRF-Token'));

        if (!is_string($cookieToken) || $cookieToken === '' || $headerToken === '' || !hash_equals($cookieToken, $headerToken)) {
            return $this->json(new Response(), 403, [
                'status' => 'error',
                'message' => 'CSRF token mismatch',
            ]);
        }

        return $handler->handle($request);
    }
}
