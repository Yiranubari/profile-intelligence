<?php

namespace Tests\Unit;

use App\Services\ExternalApiService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ExternalApiServiceTest extends TestCase
{
    private ExternalApiService $service;

    protected function setUp(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $this->service = new ExternalApiService($logger);
    }

    public function testClassifyAgeGroupChild(): void
    {
        $method = new \ReflectionMethod(ExternalApiService::class, 'classifyAgeGroup');
        $method->setAccessible(true);

        $this->assertEquals('child', $method->invoke($this->service, 0));
        $this->assertEquals('child', $method->invoke($this->service, 5));
        $this->assertEquals('child', $method->invoke($this->service, 12));
    }

    public function testClassifyAgeGroupTeenager(): void
    {
        $method = new \ReflectionMethod(ExternalApiService::class, 'classifyAgeGroup');
        $method->setAccessible(true);

        $this->assertEquals('teenager', $method->invoke($this->service, 13));
        $this->assertEquals('teenager', $method->invoke($this->service, 16));
        $this->assertEquals('teenager', $method->invoke($this->service, 19));
    }

    public function testClassifyAgeGroupAdult(): void
    {
        $method = new \ReflectionMethod(ExternalApiService::class, 'classifyAgeGroup');
        $method->setAccessible(true);

        $this->assertEquals('adult', $method->invoke($this->service, 20));
        $this->assertEquals('adult', $method->invoke($this->service, 35));
        $this->assertEquals('adult', $method->invoke($this->service, 59));
    }

    public function testClassifyAgeGroupSenior(): void
    {
        $method = new \ReflectionMethod(ExternalApiService::class, 'classifyAgeGroup');
        $method->setAccessible(true);

        $this->assertEquals('senior', $method->invoke($this->service, 60));
        $this->assertEquals('senior', $method->invoke($this->service, 75));
        $this->assertEquals('senior', $method->invoke($this->service, 100));
    }
}
