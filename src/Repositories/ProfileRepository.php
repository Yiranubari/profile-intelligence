<?php

namespace App\Repositories;

use PDO;

class ProfileRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO profiles 
            (id, name, gender, gender_probability, sample_size, age, age_group, country_id, country_probability, created_at) 
            VALUES 
            (:id, :name, :gender, :gender_probability, :sample_size, :age, :age_group, :country_id, :country_probability, :created_at)'
        );
        $stmt->execute($data);
        return $this->findById($data['id']);
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM profiles WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM profiles WHERE LOWER(name) = LOWER(:name)');
        $stmt->execute(['name' => $name]);
        return $stmt->fetch() ?: null;
    }

    public function findAll(array $filters = []): array
    {
        $query = 'SELECT id, name, gender, age, age_group, country_id FROM profiles';
        $conditions = [];
        $params = [];

        $allowedFilters = ['gender', 'country_id', 'age_group'];
        foreach ($allowedFilters as $filter) {
            if (isset($filters[$filter])) {
                $conditions[] = "LOWER({$filter}) = LOWER(:{$filter})";
                $params[$filter] = $filters[$filter];
            }
        }

        if (!empty($conditions)) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM profiles WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() === 1;
    }
}
