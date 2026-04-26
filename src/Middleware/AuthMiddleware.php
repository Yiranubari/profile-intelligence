<?php

namespace App\Middleware;

use App\Exceptions\UnauthorizedException;
use App\Repositories\UserRepository;
use App\Services\TokenService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddleware implements MiddlewareInterface
{
    use JsonResponseTrait;

    public function __construct(
        private TokenService $tokenService,
        private UserRepository $users
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->extractAccessToken($request);

        if ($token === null) {
            return $this->json(new Response(), 401, [
                'status' => 'error',
                'message' => 'Unauthorized',
            ]);
        }

        try {
            $payload = $this->tokenService->validateAccessToken($token);
        } catch (UnauthorizedException $e) {
            return $this->json(new Response(), 401, [
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }

        $userId = $payload['sub'] ?? null;
        if ($userId === null) {
            return $this->json(new Response(), 401, [
                'status' => 'error',
                'message' => 'Unauthorized',
            ]);
        }

        $user = $this->users->findById((string) $userId);
        if ($user === null) {
            return $this->json(new Response(), 401, [
                'status' => 'error',
                'message' => 'Unauthorized',
            ]);
        }

        if ((int) ($user['is_active'] ?? 0) === 0) {
            return $this->json(new Response(), 403, [
                'status' => 'error',
                'message' => 'Account is disabled',
            ]);
        }

        $request = $request->withAttribute('user', $user);
        return $handler->handle($request);
    }

    private function extractAccessToken(ServerRequestInterface $request): ?string
    {
        $authorization = trim($request->getHeaderLine('Authorization'));
        if (preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches) === 1) {
            return $matches[1];
        }

        $cookies = $request->getCookieParams();
        $cookieToken = $cookies['access_token'] ?? null;

        return is_string($cookieToken) && $cookieToken !== '' ? $cookieToken : null;
    }
}
