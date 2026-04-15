<?php

namespace Tests\Integration;

use App\Database\Database;
use App\Middleware\CorsMiddleware;
use App\Middleware\ErrorHandlerMiddleware;
use App\Middleware\JsonBodyParserMiddleware;
use App\Middleware\LoggerMiddleware;
use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class ProfileApiTest extends TestCase
{
    private \Slim\App $app;

    protected function setUp(): void
    {
        // Reset singleton for in-memory DB
        $reflection = new \ReflectionClass(Database::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, null);

        putenv('DB_PATH=:memory:');

        require __DIR__ . '/../../config/app.php';
        $container = require __DIR__ . '/../../config/dependencies.php';

        $mockExternalApi = $this->createMock(\App\Services\ExternalApiService::class);
        $mockExternalApi->method('fetchProfileData')
            ->willReturn([
                'gender' => 'female',
                'gender_probability' => 0.99,
                'sample_size' => 1234,
                'age' => 28,
                'age_group' => 'adult',
                'country_id' => 'US',
                'country_probability' => 0.85,
            ]);

        $container->set(\App\Services\ExternalApiService::class, $mockExternalApi);

        AppFactory::setContainer($container);
        $this->app = AppFactory::create();

        $this->app->add(JsonBodyParserMiddleware::class);
        $this->app->add(ErrorHandlerMiddleware::class);
        $this->app->add(LoggerMiddleware::class);
        $this->app->add(CorsMiddleware::class);

        $routes = require __DIR__ . '/../../src/Routes/api.php';
        $routes($this->app);
    }

    private function request(string $method, string $path, array $body = null): \Psr\Http\Message\ResponseInterface
    {
        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest($method, $path);

        if ($body !== null) {
            $request = $request->withHeader('Content-Type', 'application/json')
                ->withParsedBody($body);
        }

        return $this->app->handle($request);
    }

    private function decode(\Psr\Http\Message\ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true);
    }

    // POST /api/profiles
    public function testCreateProfileReturns201(): void
    {
        $response = $this->request('POST', '/api/profiles', ['name' => 'emma']);
        $this->assertEquals(201, $response->getStatusCode());

        $body = $this->decode($response);
        $this->assertEquals('success', $body['status']);
        $this->assertArrayHasKey('data', $body);
        $this->assertEquals('emma', $body['data']['name']);
    }

    public function testCreateProfileResponseStructure(): void
    {
        $response = $this->request('POST', '/api/profiles', ['name' => 'emma']);
        $data = $this->decode($response)['data'];

        $requiredKeys = ['id', 'name', 'gender', 'gender_probability', 'sample_size', 'age', 'age_group', 'country_id', 'country_probability', 'created_at'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $data);
        }
    }

    public function testCreateProfileIdempotency(): void
    {
        $this->request('POST', '/api/profiles', ['name' => 'emma']);
        $response = $this->request('POST', '/api/profiles', ['name' => 'emma']);

        $this->assertEquals(200, $response->getStatusCode());
        $body = $this->decode($response);
        $this->assertEquals('success', $body['status']);
        $this->assertEquals('Profile already exists', $body['message']);
    }

    public function testCreateProfileMissingNameReturns400(): void
    {
        $response = $this->request('POST', '/api/profiles', []);
        $this->assertEquals(400, $response->getStatusCode());

        $body = $this->decode($response);
        $this->assertEquals('error', $body['status']);
    }

    public function testCreateProfileEmptyNameReturns400(): void
    {
        $response = $this->request('POST', '/api/profiles', ['name' => '']);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testCreateProfileInvalidTypeReturns422(): void
    {
        $response = $this->request('POST', '/api/profiles', ['name' => 123]);
        $this->assertEquals(422, $response->getStatusCode());
    }

    // GET /api/profiles/{id}
    public function testGetProfileReturns200(): void
    {
        $created = $this->decode($this->request('POST', '/api/profiles', ['name' => 'emma']));
        $id = $created['data']['id'];

        $response = $this->request('GET', "/api/profiles/{$id}");
        $this->assertEquals(200, $response->getStatusCode());

        $body = $this->decode($response);
        $this->assertEquals('success', $body['status']);
        $this->assertEquals($id, $body['data']['id']);
    }

    public function testGetProfileNotFoundReturns404(): void
    {
        $response = $this->request('GET', '/api/profiles/nonexistent-id');
        $this->assertEquals(404, $response->getStatusCode());

        $body = $this->decode($response);
        $this->assertEquals('error', $body['status']);
    }

    // GET /api/profiles
    public function testGetAllProfilesReturns200(): void
    {
        $this->request('POST', '/api/profiles', ['name' => 'emma']);

        $response = $this->request('GET', '/api/profiles');
        $this->assertEquals(200, $response->getStatusCode());

        $body = $this->decode($response);
        $this->assertEquals('success', $body['status']);
        $this->assertArrayHasKey('count', $body);
        $this->assertArrayHasKey('data', $body);
    }

    public function testGetAllProfilesReturnsCorrectCount(): void
    {
        $this->request('POST', '/api/profiles', ['name' => 'emma']);
        $this->request('POST', '/api/profiles', ['name' => 'john']);

        $response = $this->request('GET', '/api/profiles');
        $body = $this->decode($response);

        $this->assertEquals(2, $body['count']);
    }

    public function testGetAllProfilesEmptyReturns200(): void
    {
        $response = $this->request('GET', '/api/profiles');
        $body = $this->decode($response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(0, $body['count']);
        $this->assertEmpty($body['data']);
    }

    public function testGetAllProfilesWithGenderFilter(): void
    {
        $this->request('POST', '/api/profiles', ['name' => 'emma']);

        $factory = new ServerRequestFactory();
        $request = $factory->createServerRequest('GET', '/api/profiles')
            ->withQueryParams(['gender' => 'female']);

        $response = $this->app->handle($request);
        $body = $this->decode($response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertGreaterThanOrEqual(0, $body['count']);
    }

    // DELETE /api/profiles/{id}
    public function testDeleteProfileReturns204(): void
    {
        $created = $this->decode($this->request('POST', '/api/profiles', ['name' => 'emma']));
        $id = $created['data']['id'];

        $response = $this->request('DELETE', "/api/profiles/{$id}");
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testDeleteProfileNotFoundReturns404(): void
    {
        $response = $this->request('DELETE', '/api/profiles/nonexistent-id');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDeletedProfileCannotBeRetrieved(): void
    {
        $created = $this->decode($this->request('POST', '/api/profiles', ['name' => 'emma']));
        $id = $created['data']['id'];

        $this->request('DELETE', "/api/profiles/{$id}");

        $response = $this->request('GET', "/api/profiles/{$id}");
        $this->assertEquals(404, $response->getStatusCode());
    }

    // CORS
    public function testCorsHeaderPresent(): void
    {
        $response = $this->request('GET', '/api/profiles');
        $this->assertEquals('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }
}
