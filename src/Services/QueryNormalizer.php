<?php

namespace App\Services;

class QueryNormalizer
{
    public static function normalize(array $filters): array
    {
        $stringFields = ['gender', 'country_id', 'age_group', 'sort_by', 'order'];
        $intFields = ['min_age', 'max_age', 'page', 'limit'];
        $floatFields = ['min_gender_probability', 'min_country_probability'];
        $defaults = ['page' => 1, 'limit' => 10, 'order' => 'asc'];

        foreach ($stringFields as $field) {
            if (isset($filters[$field]) && is_string($filters[$field])) {
                $filters[$field] = strtolower(trim($filters[$field]));
            }
        }

        foreach ($intFields as $field) {
            if (isset($filters[$field])) {
                $filters[$field] = (int)$filters[$field];
            }
        }

        foreach ($floatFields as $field) {
            if (isset($filters[$field])) {
                $filters[$field] = (float)$filters[$field];
            }
        }

        foreach ($filters as $key => $value) {
            if ($value === '' || $value === null) {
                unset($filters[$key]);
            }
        }

        foreach ($defaults as $key => $defaultValue) {
            if (isset($filters[$key]) && $filters[$key] === $defaultValue) {
                unset($filters[$key]);
            }
        }

        ksort($filters);

        return $filters;
    }
}
