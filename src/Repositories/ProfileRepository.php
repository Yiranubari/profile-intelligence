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
                (id, name, gender, gender_probability, sample_size, age, age_group, country_id, country_name, country_probability, created_at)
                VALUES 
                (:id, :name, :gender, :gender_probability, :sample_size, :age, :age_group, :country_id, :country_name, :country_probability, :created_at)'
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
        $selectQuery = 'SELECT id, name, gender, age, age_group, country_id, country_name FROM profiles';
        $countQuery = 'SELECT COUNT(*) FROM profiles';

        $conditions = [];
        $params = [];

        $allowedStringFilters = ['gender', 'country_id', 'age_group'];
        foreach ($allowedStringFilters as $filter) {
            if (isset($filters[$filter])) {
                $conditions[] = "LOWER({$filter}) = LOWER(:{$filter})";
                $params[$filter] = $filters[$filter];
            }
        }

        if (isset($filters['min_age'])) {
            $conditions[] = "age >= :min_age";
            $params['min_age'] = (int) $filters['min_age'];
        }
        if (isset($filters['max_age'])) {
            $conditions[] = "age <= :max_age";
            $params['max_age'] = (int) $filters['max_age'];
        }
        if (isset($filters['min_gender_probability'])) {
            $conditions[] = "gender_probability >= :min_gender_probability";
            $params['min_gender_probability'] = (float) $filters['min_gender_probability'];
        }
        if (isset($filters['min_country_probability'])) {
            $conditions[] = "country_probability >= :min_country_probability";
            $params['min_country_probability'] = (float) $filters['min_country_probability'];
        }

        $whereClause = '';
        if (!empty($conditions)) {
            $whereClause = ' WHERE ' . implode(' AND ', $conditions);
        }

        $selectQuery .= $whereClause;
        $countQuery .= $whereClause;

        $stmtCount = $this->pdo->prepare($countQuery);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $allowedSortColumns = ['age', 'created_at', 'gender_probability'];
        if (isset($filters['sort_by']) && in_array($filters['sort_by'], $allowedSortColumns, true)) {
            $order = isset($filters['order']) && strtolower($filters['order']) === 'desc' ? 'DESC' : 'ASC';
            $selectQuery .= " ORDER BY {$filters['sort_by']} {$order}";
        }

        $page = isset($filters['page']) ? (int) $filters['page'] : 1;
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 10;
        if ($page < 1) $page = 1;
        if ($limit < 1) $limit = 10;
        if ($limit > 50) $limit = 50;

        $offset = ($page - 1) * $limit;

        $selectQuery .= " LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($selectQuery);

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        $results = $stmt->fetchAll() ?: [];

        return [
            'data' => $results,
            'total' => $total
        ];
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM profiles WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() === 1;
    }
}
