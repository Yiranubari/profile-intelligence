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
    id                 TEXT    NOT NULL PRIMARY KEY,
    name               TEXT    NOT NULL UNIQUE,
    gender             TEXT    NOT NULL,
    gender_probability REAL    NOT NULL,
    sample_size        INTEGER NOT NULL,
    age                INTEGER NOT NULL,
    age_group          TEXT    NOT NULL,
    country_id         TEXT    NOT NULL,
    country_probability REAL   NOT NULL,
    created_at         TEXT    NOT NULL,
    country_name       TEXT    NOT NULL
)"
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
