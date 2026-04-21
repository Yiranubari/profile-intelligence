<?php

namespace App\Parsers;

class QueryParser
{
    private array $countryNames = [
        'uae' => 'AE',
        'united arab emirates' => 'AE',
        'argentina' => 'AR',
        'austria' => 'AT',
        'australia' => 'AU',
        'belgium' => 'BE',
        'brazil' => 'BR',
        'canada' => 'CA',
        'switzerland' => 'CH',
        'china' => 'CN',
        'colombia' => 'CO',
        'germany' => 'DE',
        'denmark' => 'DK',
        'egypt' => 'EG',
        'spain' => 'ES',
        'finland' => 'FI',
        'france' => 'FR',
        'uk' => 'GB',
        'united kingdom' => 'GB',
        'greece' => 'GR',
        'hong kong' => 'HK',
        'indonesia' => 'ID',
        'ireland' => 'IE',
        'israel' => 'IL',
        'india' => 'IN',
        'italy' => 'IT',
        'japan' => 'JP',
        'kenya' => 'KE',
        'south korea' => 'KR',
        'mexico' => 'MX',
        'malaysia' => 'MY',
        'nigeria' => 'NG',
        'netherlands' => 'NL',
        'norway' => 'NO',
        'new zealand' => 'NZ',
        'philippines' => 'PH',
        'poland' => 'PL',
        'portugal' => 'PT',
        'russia' => 'RU',
        'saudi arabia' => 'SA',
        'sweden' => 'SE',
        'singapore' => 'SG',
        'thailand' => 'TH',
        'turkey' => 'TR',
        'taiwan' => 'TW',
        'ukraine' => 'UA',
        'us' => 'US',
        'usa' => 'US',
        'united states' => 'US',
        'vietnam' => 'VN',
        'south africa' => 'ZA',
        'ghana' => 'GH',
        'tanzania' => 'TZ',
        'uganda' => 'UG',
        'benin' => 'BJ',
        'ethiopia' => 'ET',
        'senegal' => 'SN',
        'cameroon' => 'CM',
        'angola' => 'AO',
        'zambia' => 'ZM',
        'zimbabwe' => 'ZW',
    ];

    public function parse(string $query): array
    {
        $filters = [];
        $lowerQuery = strtolower($query);

        // Gender parsing
        $hasFemale = (bool) preg_match('/\bfemales?\b/', $lowerQuery);
        $hasMale = (bool) preg_match('/\bmales?\b/', $lowerQuery);

        if ($hasFemale && !$hasMale) {
            $filters['gender'] = 'female';
        } elseif ($hasMale && !$hasFemale) {
            $filters['gender'] = 'male';
        }

        // Age group parsing
        if (str_contains($lowerQuery, 'child') || str_contains($lowerQuery, 'children')) {
            $filters['age_group'] = 'child';
        } elseif (str_contains($lowerQuery, 'teenager') || str_contains($lowerQuery, 'teenagers')) {
            $filters['age_group'] = 'teenager';
        } elseif (str_contains($lowerQuery, 'adult') || str_contains($lowerQuery, 'adults')) {
            $filters['age_group'] = 'adult';
        } elseif (str_contains($lowerQuery, 'senior') || str_contains($lowerQuery, 'seniors')) {
            $filters['age_group'] = 'senior';
        }

        // Young mapping
        if (str_contains($lowerQuery, 'young')) {
            $filters['min_age'] = 16;
            $filters['max_age'] = 24;
        }

        // Age expressions
        if (preg_match('/(?:above|over|older than)\s+(\d+)/', $lowerQuery, $matches)) {
            $filters['min_age'] = (int) $matches[1];
        }
        if (preg_match('/(?:below|under|younger than)\s+(\d+)/', $lowerQuery, $matches)) {
            $filters['max_age'] = (int) $matches[1];
        }

        // Country matching
        foreach ($this->countryNames as $countryName => $countryCode) {
            if (str_contains($lowerQuery, $countryName)) {
                $filters['country_id'] = $countryCode;
                break;
            }
        }

        // Uninterpretable fallback
        if (empty($filters)) {
            return ['_uninterpretable' => true];
        }

        return $filters;
    }
}
