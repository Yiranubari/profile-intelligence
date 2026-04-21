<?php

namespace App\Services;

use App\Exceptions\ExternalApiException;
use Psr\Log\LoggerInterface;


class ExternalApiService
{
    private array $countryMap = [
        'AE' => 'United Arab Emirates',
        'AR' => 'Argentina',
        'AT' => 'Austria',
        'AU' => 'Australia',
        'BE' => 'Belgium',
        'BR' => 'Brazil',
        'CA' => 'Canada',
        'CH' => 'Switzerland',
        'CN' => 'China',
        'CO' => 'Colombia',
        'DE' => 'Germany',
        'DK' => 'Denmark',
        'EG' => 'Egypt',
        'ES' => 'Spain',
        'FI' => 'Finland',
        'FR' => 'France',
        'GB' => 'United Kingdom',
        'GR' => 'Greece',
        'HK' => 'Hong Kong',
        'ID' => 'Indonesia',
        'IE' => 'Ireland',
        'IL' => 'Israel',
        'IN' => 'India',
        'IT' => 'Italy',
        'JP' => 'Japan',
        'KR' => 'South Korea',
        'MX' => 'Mexico',
        'MY' => 'Malaysia',
        'NG' => 'Nigeria',
        'NL' => 'Netherlands',
        'NO' => 'Norway',
        'NZ' => 'New Zealand',
        'PH' => 'Philippines',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'RU' => 'Russia',
        'SA' => 'Saudi Arabia',
        'SE' => 'Sweden',
        'SG' => 'Singapore',
        'TH' => 'Thailand',
        'TR' => 'Turkey',
        'TW' => 'Taiwan',
        'UA' => 'Ukraine',
        'US' => 'United States',
        'VN' => 'Vietnam',
        'ZA' => 'South Africa',
    ];

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
            throw new ExternalApiException('Genderize returned an invalid response');
        }

        $data = json_decode($response, true);

        if (!is_array($data) || !isset($data['gender']) || !isset($data['count']) || $data['gender'] === null || $data['count'] === 0) {
            $this->logger->error("Invalid Genderize API response for name: {$name}");
            throw new ExternalApiException('Genderize returned an invalid response');
        }

        return [
            'gender' => $data['gender'],
            'gender_probability' => round($data['probability'], 2),
            'sample_size' => $data['count'],
        ];
    }

    private function fetchAgeData(string $name): array
    {
        $url = "https://api.agify.io?name=" . urlencode($name);
        $response = @file_get_contents($url);

        if ($response === false) {
            $this->logger->error("Agify API request failed for name: {$name}");
            throw new ExternalApiException('Agify returned an invalid response');
        }

        $data = json_decode($response, true);

        if (!is_array($data) || !isset($data['age']) || $data['age'] === null) {
            $this->logger->error("Invalid Agify API response for name: {$name}");
            throw new ExternalApiException('Agify returned an invalid response');
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
            throw new ExternalApiException('Nationalize returned an invalid response');
        }

        $data = json_decode($response, true);

        if (!is_array($data) || empty($data['country'])) {
            $this->logger->error("Invalid Nationalize API response for name: {$name}");
            throw new ExternalApiException('Nationalize returned an invalid response');
        }

        $bestMatch = null;
        $highestProbability = -1;

        foreach ($data['country'] as $country) {
            if ($country['probability'] > $highestProbability) {
                $highestProbability = $country['probability'];
                $bestMatch = $country;
            }
        }

        $countryId = $bestMatch['country_id'];

        return [
            'country_id' => $countryId,
            'country_probability' => round($bestMatch['probability'], 2),
            'country_name' => $this->countryMap[$countryId] ?? $countryId,
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
