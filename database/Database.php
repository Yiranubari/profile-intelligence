<?php

namespace App\Database;

use PDO;
use PDOException;


class Database
{
    private static ?Database $instance = null;
    private PDO $connection;

    private function __construct()
    {
        try {
            $path = getenv('DB_PATH') ?: __DIR__ . '/../database/profiles.db';
            $this->connection = new PDO('sqlite:' . $path);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->initializeSchema();
            $this->migrateSchema();
        } catch (PDOException $e) {
            throw new \RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }


    public static function getInstance()
    {
        $checkInstance = self::$instance;
        if ($checkInstance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    private function initializeSchema()
    {
        $this->connection->exec(
            "CREATE TABLE IF NOT EXISTS profiles (
            id                  TEXT    NOT NULL PRIMARY KEY,
            name                TEXT    NOT NULL UNIQUE,
            gender              TEXT    NOT NULL,
            gender_probability  REAL    NOT NULL,
            sample_size         INTEGER NOT NULL,
            age                 INTEGER NOT NULL,
            age_group           TEXT    NOT NULL,
            country_id          TEXT    NOT NULL,
            country_probability REAL    NOT NULL,
            created_at          TEXT    NOT NULL,
            country_name        TEXT    NOT NULL
        )"
        );

        $this->connection->exec(
            "CREATE TABLE IF NOT EXISTS users (
            id            TEXT    NOT NULL PRIMARY KEY,
            github_id     TEXT    NOT NULL UNIQUE,
            username      TEXT    NOT NULL,
            email         TEXT,
            avatar_url    TEXT,
            role          TEXT    NOT NULL DEFAULT 'analyst',
            is_active     INTEGER NOT NULL DEFAULT 1,
            last_login_at TEXT,
            created_at    TEXT    NOT NULL
        )"
        );

        $this->connection->exec(
            "CREATE TABLE IF NOT EXISTS refresh_tokens (
            id         TEXT    NOT NULL PRIMARY KEY,
            user_id    TEXT    NOT NULL,
            token_hash TEXT    NOT NULL UNIQUE,
            expires_at TEXT    NOT NULL,
            revoked    INTEGER NOT NULL DEFAULT 0,
            created_at TEXT    NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
        );

        $this->connection->exec(
            "CREATE TABLE IF NOT EXISTS rate_limits (
            key          TEXT    NOT NULL,
            window_start TEXT    NOT NULL,
            count        INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY (key, window_start)
        )"
        );

        $this->connection->exec(
            "CREATE TABLE IF NOT EXISTS auth_sessions (
        state          TEXT    NOT NULL PRIMARY KEY,
        code_challenge TEXT,
        client_type    TEXT    NOT NULL,
        auth_code      TEXT    UNIQUE,
        user_id        TEXT,
        consumed       INTEGER NOT NULL DEFAULT 0,
        expires_at     TEXT    NOT NULL,
        created_at     TEXT    NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )"
        );

        $this->connection->exec(
            "CREATE INDEX IF NOT EXISTS idx_auth_sessions_auth_code ON auth_sessions(auth_code)"
        );

        $this->connection->exec(
            "CREATE INDEX IF NOT EXISTS idx_refresh_tokens_user ON refresh_tokens(user_id)"
        );
        $this->connection->exec(
            "CREATE INDEX IF NOT EXISTS idx_refresh_tokens_hash ON refresh_tokens(token_hash)"
        );
    }

    private function migrateSchema()
    {
        try {
            $this->connection->exec("ALTER TABLE profiles ADD COLUMN country_name TEXT NOT NULL DEFAULT ''");
        } catch (PDOException $e) {
        }
    }
}
