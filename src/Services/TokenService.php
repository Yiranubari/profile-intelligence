<?php

namespace App\Services;

use App\Exceptions\UnauthorizedException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

class TokenService
{
    public function __construct(
        private string $secret,
        private int $accessTtl = 180,
        private int $refreshTtl = 300
    ) {}

    public function generateAccessToken(array $user): string
    {
        $now = time();
        $subject = $user['id'] ?? null;

        if ($subject === null) {
            throw new UnauthorizedException('Invalid user payload');
        }

        $payload = [
            'sub' => $subject,
            'role' => $user['role'] ?? null,
            'username' => $user['username'] ?? null,
            'iat' => $now,
            'exp' => $now + $this->accessTtl,
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function generateRefreshToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function validateAccessToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            return (array) $decoded;
        } catch (ExpiredException $e) {
            throw new UnauthorizedException('Access token expired');
        } catch (Throwable $e) {
            throw new UnauthorizedException('Invalid access token');
        }
    }

    public function hashRefreshToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
