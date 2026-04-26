<?php

namespace App\Services;

class CsvExportService
{
    public function streamCsv(array $rows, \Psr\Http\Message\ResponseInterface $response): \Psr\Http\Message\ResponseInterface
    {
        $columns = ['id', 'name', 'gender', 'gender_probability', 'age', 'age_group', 'country_id', 'country_name', 'country_probability', 'created_at'];

        $body = $response->getBody();

        // Header row
        $body->write($this->csvLine($columns));

        foreach ($rows as $row) {
            $values = array_map(fn($col) => $row[$col] ?? '', $columns);
            $body->write($this->csvLine($values));
        }

        $filename = 'profiles_' . (new \DateTime('now', new \DateTimeZone('UTC')))->format('Ymd_His') . '.csv';

        return $response
            ->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->withStatus(200);
    }

    private function csvLine(array $fields): string
    {
        $escaped = array_map(function ($field) {
            $str = (string) $field;
            if (preg_match('/[",\n\r]/', $str)) {
                $str = '"' . str_replace('"', '""', $str) . '"';
            }
            return $str;
        }, $fields);

        return implode(',', $escaped) . "\r\n";
    }
}
