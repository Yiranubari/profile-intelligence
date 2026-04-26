<?php

namespace App\Repositories;

use DateTime;
use DateTimeZone;
use PDO;
use RuntimeException;

class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByGithubId(string $githubId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE github_id = :github_id');
        $stmt->execute(['github_id' => $githubId]);

        return $stmt->fetch() ?: null;
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users
				(id, github_id, username, email, avatar_url, role, created_at)
			 VALUES
				(:id, :github_id, :username, :email, :avatar_url, :role, :created_at)'
        );
        $stmt->execute($data);

        $user = $this->findById($data['id']);
        if ($user === null) {
            throw new RuntimeException('User was not found after insert');
        }

        return $user;
    }

    public function updateLastLogin(string $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET last_login_at = :now WHERE id = :id');
        $stmt->execute([
            'now' => (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            'id' => $id,
        ]);
    }

    public function updateProfile(string $id, array $data): array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users
			 SET username = :username,
				 email = :email,
				 avatar_url = :avatar_url
			 WHERE id = :id'
        );

        $stmt->execute([
            'username' => $data['username'] ?? null,
            'email' => $data['email'] ?? null,
            'avatar_url' => $data['avatar_url'] ?? null,
            'id' => $id,
        ]);

        return $this->findById($id) ?: [];
    }
}
