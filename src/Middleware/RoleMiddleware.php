<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class RoleMiddleware implements MiddlewareInterface
{
    use JsonResponseTrait;

    public function __construct(private string $requiredRole) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute('user');

        if ($user === null) {
            return $this->json(new Response(), 401, [
                'status' => 'error',
                'message' => 'Unauthorized',
            ]);
        }

        if (($user['role'] ?? null) !== $this->requiredRole) {
            return $this->json(new Response(), 403, [
                'status' => 'error',
                'message' => 'Forbidden',
            ]);
        }

        return $handler->handle($request);
    }
}
