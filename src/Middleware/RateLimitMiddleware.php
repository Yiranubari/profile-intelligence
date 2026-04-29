<?php

namespace App\Middleware;

use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class RateLimitMiddleware implements MiddlewareInterface
{
    use JsonResponseTrait;

    public function __construct(
        private PDO $pdo,
        private int $limit,
        private string $scope
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $identifier = $this->identifier($request);
        $key = "{$this->scope}:{$identifier}";
        $window = gmdate('Y-m-d\TH:i:00\Z');

        $upsert = $this->pdo->prepare(
            'INSERT INTO rate_limits (key, window_start, count)
			 VALUES (:key, :window, 1)
			 ON CONFLICT(key, window_start) DO UPDATE SET count = count + 1'
        );
        $upsert->execute([
            'key' => $key,
            'window' => $window,
        ]);

        $countStmt = $this->pdo->prepare('SELECT count FROM rate_limits WHERE key = :key AND window_start = :window');
        $countStmt->execute([
            'key' => $key,
            'window' => $window,
        ]);
        $count = (int) ($countStmt->fetchColumn() ?: 0);

        $remaining = max(0, $this->limit - $count);

        if ($count > $this->limit) {
            return $this->json(new Response(), 429, [
                'status' => 'error',
                'message' => 'Too Many Requests',
            ])
                ->withHeader('Retry-After', '60')
                ->withHeader('X-RateLimit-Limit', (string) $this->limit)
                ->withHeader('X-RateLimit-Remaining', '0')
                ->withHeader('X-RateLimit-Reset', '60');
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->limit)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining)
            ->withHeader('X-RateLimit-Reset', '60');
    }

    private function identifier(ServerRequestInterface $request): string
    {
        if ($this->scope === 'auth') {
            return $this->clientIp($request);
        }

        $user = $request->getAttribute('user');
        if (is_array($user) && !empty($user['id'])) {
            return (string) $user['id'];
        }

        return $this->clientIp($request);
    }

    private function clientIp(ServerRequestInterface $request): string
    {
        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if ($forwarded !== '') {
            $parts = explode(',', $forwarded);
            return trim($parts[0]);
        }

        return $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
    }
}
