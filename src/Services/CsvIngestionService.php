<?php

namespace App\Services;

use App\Helpers\UuidHelper;
use PDO;
use RuntimeException;

class CsvIngestionService
{
    private const CHUNK_SIZE = 1000;

    private const REQUIRED_HEADER = [
        'name',
        'gender',
        'gender_probability',
        'sample_size',
        'age',
        'age_group',
        'country_id',
        'country_name',
        'country_probability',
    ];

    public function __construct(private PDO $pdo) {}

    public function ingest(string $filePath): array
    {
        $totalRows = 0;
        $inserted = 0;
        $reasons = [
            'duplicate_name' => 0,
            'invalid_age' => 0,
            'missing_fields' => 0,
            'invalid_gender' => 0,
            'malformed_row' => 0,
        ];

        $handle = @fopen($filePath, 'r');
        if ($handle === false) {
            throw new RuntimeException('Could not open uploaded file');
        }

        $header = fgetcsv($handle);
        if ($header === false || $header !== self::REQUIRED_HEADER) {
            fclose($handle);
            throw new RuntimeException('Invalid CSV header');
        }

        $buffer = [];

        while (($row = fgetcsv($handle)) !== false) {
            $totalRows++;
            $buffer[] = $row;

            if (count($buffer) >= self::CHUNK_SIZE) {
                $this->processChunk($buffer, $header, $inserted, $reasons);
                $buffer = [];
            }
        }

        if (!empty($buffer)) {
            $this->processChunk($buffer, $header, $inserted, $reasons);
        }

        fclose($handle);

        return [
            'total_rows' => $totalRows,
            'inserted' => $inserted,
            'skipped' => $totalRows - $inserted,
            'reasons' => array_filter($reasons),
        ];
    }

    private function processChunk(array $rows, array $header, int &$inserted, array &$reasons): void
    {
        $validRows = [];

        foreach ($rows as $row) {
            $result = $this->validateRow($row, $header);
            if ($result['valid']) {
                $validRows[] = $result['data'];
            } else {
                $reasons[$result['reason']]++;
            }
        }

        if (empty($validRows)) {
            return;
        }

        $insertedNow = $this->bulkInsert($validRows);
        $inserted += $insertedNow;
        $reasons['duplicate_name'] += count($validRows) - $insertedNow;
    }

    private function validateRow(array $row, array $header): array
    {
        if (count($row) !== count($header)) {
            return ['valid' => false, 'reason' => 'malformed_row'];
        }

        $data = array_combine($header, $row);

        $name = trim($data['name'] ?? '');
        if ($name === '') {
            return ['valid' => false, 'reason' => 'missing_fields'];
        }

        $gender = strtolower(trim($data['gender'] ?? ''));
        if (!in_array($gender, ['male', 'female'], true)) {
            return ['valid' => false, 'reason' => 'invalid_gender'];
        }

        if (!is_numeric($data['age'] ?? '')) {
            return ['valid' => false, 'reason' => 'invalid_age'];
        }
        $age = (int) $data['age'];
        if ($age < 0 || $age > 150) {
            return ['valid' => false, 'reason' => 'invalid_age'];
        }

        $countryId = trim($data['country_id'] ?? '');
        if ($countryId === '') {
            return ['valid' => false, 'reason' => 'missing_fields'];
        }

        $ageGroup = trim($data['age_group'] ?? '');
        if ($ageGroup === '') {
            $ageGroup = $this->classifyAgeGroup($age);
        }

        return [
            'valid' => true,
            'data' => [
                'id' => UuidHelper::generate(),
                'name' => $name,
                'gender' => $gender,
                'gender_probability' => is_numeric($data['gender_probability'] ?? '')
                    ? (float) $data['gender_probability'] : 0.0,
                'sample_size' => is_numeric($data['sample_size'] ?? '')
                    ? (int) $data['sample_size'] : 0,
                'age' => $age,
                'age_group' => $ageGroup,
                'country_id' => strtoupper($countryId),
                'country_name' => trim($data['country_name'] ?? ''),
                'country_probability' => is_numeric($data['country_probability'] ?? '')
                    ? (float) $data['country_probability'] : 0.0,
                'created_at' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            ],
        ];
    }

    private function bulkInsert(array $rows): int
    {
        $columns = ['id', 'name', 'gender', 'gender_probability', 'sample_size', 'age', 'age_group', 'country_id', 'country_name', 'country_probability', 'created_at'];
        $placeholderRow = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $placeholders = implode(',', array_fill(0, count($rows), $placeholderRow));

        $sql = 'INSERT INTO profiles (' . implode(',', $columns) . ') VALUES ' . $placeholders . ' ON CONFLICT(name) DO NOTHING';

        $values = [];
        foreach ($rows as $row) {
            foreach ($columns as $col) {
                $values[] = $row[$col];
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);

        return $stmt->rowCount();
    }

    private function classifyAgeGroup(int $age): string
    {
        if ($age <= 12) return 'child';
        if ($age <= 19) return 'teenager';
        if ($age <= 59) return 'adult';
        return 'senior';
    }
}
