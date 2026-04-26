<?php

namespace App\Repositories;

use PDO;

class RefreshTokenRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO refresh_tokens
				(id, user_id, token_hash, expires_at, created_at)
			 VALUES
				(:id, :user_id, :token_hash, :expires_at, :created_at)'
        );

        $stmt->execute($data);
    }

    public function findByHash(string $hash): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM refresh_tokens WHERE token_hash = :hash AND revoked = 0');
        $stmt->execute(['hash' => $hash]);

        return $stmt->fetch() ?: null;
    }

    public function revoke(string $hash): void
    {
        $stmt = $this->pdo->prepare('UPDATE refresh_tokens SET revoked = 1 WHERE token_hash = :hash');
        $stmt->execute(['hash' => $hash]);
    }

    public function revokeAllForUser(string $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE refresh_tokens SET revoked = 1 WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }
}
