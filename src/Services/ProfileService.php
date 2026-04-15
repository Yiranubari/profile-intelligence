<?php

namespace App\Services;

use App\Repositories\ProfileRepository;
use App\Exceptions\ProfileNotFoundException;
use App\Exceptions\DuplicateProfileException;
use App\Services\ExternalApiService;
use App\Helpers\UuidHelper;

class ProfileService
{
    public function __construct(
        private ProfileRepository $repository,
        private ExternalApiService $externalApi
    ) {}

    public function createProfile(string $name): array
    {
        $existingProfile = $this->repository->findByName($name);

        if ($existingProfile !== null) {
            throw new DuplicateProfileException($existingProfile);
        }

        $apiData = $this->externalApi->fetchProfileData($name);

        $profile = [
            'id' => UuidHelper::generate(),
            'name' => $name,
            'gender' => $apiData['gender'],
            'gender_probability' => $apiData['gender_probability'],
            'sample_size' => $apiData['sample_size'],
            'age' => $apiData['age'],
            'age_group' => $apiData['age_group'],
            'country_id' => $apiData['country_id'],
            'country_probability' => $apiData['country_probability'],
            'created_at' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
        ];

        return $this->repository->create($profile);
    }

    public function getProfile(string $id): array
    {
        $profile = $this->repository->findById($id);

        if ($profile === null) {
            throw new ProfileNotFoundException($id);
        }

        return $profile;
    }

    public function getAllProfiles(array $filters = []): array
    {
        return $this->repository->findAll($filters);
    }

    public function deleteProfile(string $id): void
    {
        if (!$this->repository->delete($id)) {
            throw new ProfileNotFoundException($id);
        }
    }
}
