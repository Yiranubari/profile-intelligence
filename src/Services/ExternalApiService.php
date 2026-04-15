<?php

namespace App\Services;

use App\Exceptions\ExternalApiException;
use Psr\Log\LoggerInterface;


class ExternalApiService
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function fetchProfileData(string $name): array
    {
        $genderData = $this->fetchGenderData($name);
        $ageData = $this->fetchAgeData($name);
        $nationalityData = $this->fetchNationalityData($name);

        return array_merge($genderData, $ageData, $nationalityData);
    }

    private function fetchGenderData(string $name): array
    {
        $url = "https://api.genderize.io?name=" . urlencode($name);
        $response = @file_get_contents($url);

        if ($response === false) {
            $this->logger->error("Genderize API request failed for name: {$name}");
            throw new ExternalApiException('Genderize');
        }

        $data = json_decode($response, true);

        if (!is_array($data) || !isset($data['gender']) || !isset($data['count']) || $data['gender'] === null || $data['count'] === 0) {
            $this->logger->error("Invalid Genderize API response for name: {$name}");
            throw new ExternalApiException('Genderize');
        }

        return [
            'gender' => $data['gender'],
            'gender_probability' => $data['probability'],
            'sample_size' => $data['count'],
        ];
    }

    private function fetchAgeData(string $name): array
    {
        $url = "https://api.agify.io?name=" . urlencode($name);
        $response = @file_get_contents($url);

        if ($response === false) {
            $this->logger->error("Agify API request failed for name: {$name}");
            throw new ExternalApiException('Agify');
        }

        $data = json_decode($response, true);

        if (!is_array($data) || !isset($data['age']) || $data['age'] === null) {
            $this->logger->error("Invalid Agify API response for name: {$name}");
            throw new ExternalApiException('Agify');
        }

        return [
            'age' => $data['age'],
            'age_group' => $this->classifyAgeGroup($data['age']),
        ];
    }

    private function fetchNationalityData(string $name): array
    {
        $url = "https://api.nationalize.io?name=" . urlencode($name);
        $response = @file_get_contents($url);

        if ($response === false) {
            $this->logger->error("Nationalize API request failed for name: {$name}");
            throw new ExternalApiException('Nationalize');
        }

        $data = json_decode($response, true);

        if (!is_array($data) || empty($data['country'])) {
            $this->logger->error("Invalid Nationalize API response for name: {$name}");
            throw new ExternalApiException('Nationalize');
        }

        $bestMatch = null;
        $highestProbability = -1;

        foreach ($data['country'] as $country) {
            if ($country['probability'] > $highestProbability) {
                $highestProbability = $country['probability'];
                $bestMatch = $country;
            }
        }

        return [
            'country_id' => $bestMatch['country_id'],
            'country_probability' => $bestMatch['probability'],
        ];
    }

    private function classifyAgeGroup(int $age): string
    {
        if ($age <= 12) {
            return 'child';
        } elseif ($age <= 19) {
            return 'teenager';
        } elseif ($age <= 59) {
            return 'adult';
        } else {
            return 'senior';
        }
    }
}
