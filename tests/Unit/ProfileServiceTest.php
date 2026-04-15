<?php

namespace Tests\Unit;

use App\Exceptions\DuplicateProfileException;
use App\Exceptions\ProfileNotFoundException;
use App\Repositories\ProfileRepository;
use App\Services\ExternalApiService;
use App\Services\ProfileService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProfileServiceTest extends TestCase
{
    private ProfileService $service;
    private ProfileRepository&MockObject $repository;
    private ExternalApiService&MockObject $externalApi;

    private array $mockProfile = [
        'id'                 => 'test-uuid',
        'name'               => 'emma',
        'gender'             => 'female',
        'gender_probability' => 0.99,
        'sample_size'        => 1234,
        'age'                => 28,
        'age_group'          => 'adult',
        'country_id'         => 'US',
        'country_probability' => 0.85,
        'created_at'         => '2026-04-01T12:00:00Z',
    ];

    protected function setUp(): void
    {
        $this->repository  = $this->createMock(ProfileRepository::class);
        $this->externalApi = $this->createMock(ExternalApiService::class);
        $this->service     = new ProfileService($this->repository, $this->externalApi);
    }

    public function testCreateProfileSuccess(): void
    {
        $this->repository->expects($this->once())
            ->method('findByName')
            ->with('emma')
            ->willReturn(null);

        $this->externalApi->expects($this->once())
            ->method('fetchProfileData')
            ->with('emma')
            ->willReturn([
                'gender'              => 'female',
                'gender_probability'  => 0.99,
                'sample_size'         => 1234,
                'age'                 => 28,
                'age_group'           => 'adult',
                'country_id'          => 'US',
                'country_probability' => 0.85,
            ]);

        $this->repository->expects($this->once())
            ->method('create')
            ->willReturn($this->mockProfile);

        $result = $this->service->createProfile('emma');
        $this->assertEquals($this->mockProfile, $result);
    }

    public function testCreateProfileThrowsDuplicateException(): void
    {
        $this->expectException(DuplicateProfileException::class);

        $this->repository->expects($this->once())
            ->method('findByName')
            ->with('emma')
            ->willReturn($this->mockProfile);

        $this->service->createProfile('emma');
    }

    public function testGetProfileSuccess(): void
    {
        $this->repository->expects($this->once())
            ->method('findById')
            ->with('test-uuid')
            ->willReturn($this->mockProfile);

        $result = $this->service->getProfile('test-uuid');
        $this->assertEquals($this->mockProfile, $result);
    }

    public function testGetProfileThrowsNotFoundException(): void
    {
        $this->expectException(ProfileNotFoundException::class);

        $this->repository->expects($this->once())
            ->method('findById')
            ->with('nonexistent-id')
            ->willReturn(null);

        $this->service->getProfile('nonexistent-id');
    }

    public function testGetAllProfilesNoFilters(): void
    {
        $this->repository->expects($this->once())
            ->method('findAll')
            ->with([])
            ->willReturn([$this->mockProfile]);

        $result = $this->service->getAllProfiles();
        $this->assertCount(1, $result);
    }

    public function testGetAllProfilesWithFilters(): void
    {
        $filters = ['gender' => 'female', 'country_id' => 'US'];

        $this->repository->expects($this->once())
            ->method('findAll')
            ->with($filters)
            ->willReturn([$this->mockProfile]);

        $result = $this->service->getAllProfiles($filters);
        $this->assertCount(1, $result);
    }

    public function testGetAllProfilesReturnsEmptyArray(): void
    {
        $this->repository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $result = $this->service->getAllProfiles();
        $this->assertEmpty($result);
    }

    public function testDeleteProfileSuccess(): void
    {
        $this->repository->expects($this->once())
            ->method('delete')
            ->with('test-uuid')
            ->willReturn(true);

        $this->service->deleteProfile('test-uuid');
        $this->assertTrue(true);
    }

    public function testDeleteProfileThrowsNotFoundException(): void
    {
        $this->expectException(ProfileNotFoundException::class);

        $this->repository->expects($this->once())
            ->method('delete')
            ->with('nonexistent-id')
            ->willReturn(false);

        $this->service->deleteProfile('nonexistent-id');
    }
}
