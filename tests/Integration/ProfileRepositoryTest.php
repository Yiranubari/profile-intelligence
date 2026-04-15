<?php

namespace Tests\Integration;

use App\Database\Database;
use App\Repositories\ProfileRepository;
use PHPUnit\Framework\TestCase;

class ProfileRepositoryTest extends TestCase
{
    private ProfileRepository $repository;

    private array $mockProfile = [
        'id'                  => '01956c5e-d557-7b2d-9e36-b6db7f9b5c2a',
        'name'                => 'emma',
        'gender'              => 'female',
        'gender_probability'  => 0.99,
        'sample_size'         => 1234,
        'age'                 => 28,
        'age_group'           => 'adult',
        'country_id'          => 'US',
        'country_probability' => 0.85,
        'created_at'          => '2026-04-01T12:00:00Z',
    ];

    protected function setUp(): void
    {
        // Reset singleton and use in-memory DB for each test
        $reflection = new \ReflectionClass(Database::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, null);

        putenv('DB_PATH=:memory:');

        $db = Database::getInstance();
        $this->repository = new ProfileRepository($db->getConnection());
    }

    public function testCreateAndFindById(): void
    {
        $this->repository->create($this->mockProfile);
        $result = $this->repository->findById($this->mockProfile['id']);

        $this->assertNotNull($result);
        $this->assertEquals($this->mockProfile['id'], $result['id']);
        $this->assertEquals($this->mockProfile['name'], $result['name']);
    }

    public function testFindByIdReturnsNullIfNotFound(): void
    {
        $result = $this->repository->findById('nonexistent-id');
        $this->assertNull($result);
    }

    public function testFindByName(): void
    {
        $this->repository->create($this->mockProfile);
        $result = $this->repository->findByName('emma');

        $this->assertNotNull($result);
        $this->assertEquals('emma', $result['name']);
    }

    public function testFindByNameCaseInsensitive(): void
    {
        $this->repository->create($this->mockProfile);

        $this->assertNotNull($this->repository->findByName('EMMA'));
        $this->assertNotNull($this->repository->findByName('Emma'));
        $this->assertNotNull($this->repository->findByName('eMmA'));
    }

    public function testFindByNameReturnsNullIfNotFound(): void
    {
        $result = $this->repository->findByName('nonexistent');
        $this->assertNull($result);
    }

    public function testFindAllReturnsAllProfiles(): void
    {
        $this->repository->create($this->mockProfile);

        $second = array_merge($this->mockProfile, [
            'id'   => '01956c5e-d557-7b2d-9e36-b6db7f9b5c2b',
            'name' => 'john',
        ]);
        $this->repository->create($second);

        $results = $this->repository->findAll();
        $this->assertCount(2, $results);
    }

    public function testFindAllWithGenderFilter(): void
    {
        $this->repository->create($this->mockProfile);

        $male = array_merge($this->mockProfile, [
            'id'     => '01956c5e-d557-7b2d-9e36-b6db7f9b5c2b',
            'name'   => 'john',
            'gender' => 'male',
        ]);
        $this->repository->create($male);

        $results = $this->repository->findAll(['gender' => 'female']);
        $this->assertCount(1, $results);
        $this->assertEquals('emma', $results[0]['name']);
    }

    public function testFindAllWithCountryFilter(): void
    {
        $this->repository->create($this->mockProfile);

        $nigerian = array_merge($this->mockProfile, [
            'id'         => '01956c5e-d557-7b2d-9e36-b6db7f9b5c2b',
            'name'       => 'chidi',
            'country_id' => 'NG',
        ]);
        $this->repository->create($nigerian);

        $results = $this->repository->findAll(['country_id' => 'NG']);
        $this->assertCount(1, $results);
        $this->assertEquals('chidi', $results[0]['name']);
    }

    public function testFindAllWithAgeGroupFilter(): void
    {
        $this->repository->create($this->mockProfile);

        $teenager = array_merge($this->mockProfile, [
            'id'        => '01956c5e-d557-7b2d-9e36-b6db7f9b5c2b',
            'name'      => 'jake',
            'age'       => 16,
            'age_group' => 'teenager',
        ]);
        $this->repository->create($teenager);

        $results = $this->repository->findAll(['age_group' => 'teenager']);
        $this->assertCount(1, $results);
        $this->assertEquals('jake', $results[0]['name']);
    }

    public function testFindAllWithMultipleFilters(): void
    {
        $this->repository->create($this->mockProfile);

        $results = $this->repository->findAll([
            'gender'    => 'female',
            'country_id' => 'US',
            'age_group' => 'adult',
        ]);

        $this->assertCount(1, $results);
    }

    public function testFindAllReturnsEmptyArray(): void
    {
        $results = $this->repository->findAll();
        $this->assertEmpty($results);
    }

    public function testFindAllFilterCaseInsensitive(): void
    {
        $this->repository->create($this->mockProfile);

        $results = $this->repository->findAll(['gender' => 'FEMALE']);
        $this->assertCount(1, $results);
    }

    public function testDeleteSuccess(): void
    {
        $this->repository->create($this->mockProfile);
        $result = $this->repository->delete($this->mockProfile['id']);

        $this->assertTrue($result);
        $this->assertNull($this->repository->findById($this->mockProfile['id']));
    }

    public function testDeleteReturnsFalseIfNotFound(): void
    {
        $result = $this->repository->delete('nonexistent-id');
        $this->assertFalse($result);
    }

    public function testCreateReturnsPersisedProfile(): void
    {
        $result = $this->repository->create($this->mockProfile);

        $this->assertEquals($this->mockProfile['id'], $result['id']);
        $this->assertEquals($this->mockProfile['name'], $result['name']);
        $this->assertEquals($this->mockProfile['gender'], $result['gender']);
    }
}
