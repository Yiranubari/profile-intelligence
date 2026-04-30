<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Database\Database;
use App\Helpers\UuidHelper;

$pdo = Database::getInstance()->getConnection();

$now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');

$users = [
    ['username' => 'test_admin',   'role' => 'admin',   'github_id' => '999000001'],
    ['username' => 'test_analyst', 'role' => 'analyst', 'github_id' => '999000002'],
];

foreach ($users as $u) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE github_id = :gh');
    $stmt->execute(['gh' => $u['github_id']]);
    if ($stmt->fetchColumn()) {
        echo "Already exists: {$u['username']}\n";
        continue;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO users (id, github_id, username, email, avatar_url, role, is_active, created_at)
         VALUES (:id, :gh, :un, :em, :av, :role, 1, :now)'
    );
    $stmt->execute([
        'id' => UuidHelper::generate(),
        'gh' => $u['github_id'],
        'un' => $u['username'],
        'em' => "{$u['username']}@example.com",
        'av' => null,
        'role' => $u['role'],
        'now' => $now,
    ]);

    echo "Seeded: {$u['username']} ({$u['role']})\n";
}
