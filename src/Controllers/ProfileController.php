<?php

namespace App\Controllers;

use App\Services\ProfileService;
use App\Validators\ProfileValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use App\Parsers\QueryParser;

class ProfileController
{
    public function __construct(
        private ProfileService $service,
        private LoggerInterface $logger
    ) {}

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody() ?? [];
        ProfileValidator::validate($body);

        $name = trim($body['name']);
        $profile = $this->service->createProfile($name);

        return $this->json($response, 201, [
            'status' => 'success',
            'data' => $profile
        ]);
    }

    public function getOne(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        $profile = $this->service->getProfile($id);

        return $this->json($response, 200, [
            'status' => 'success',
            'data' => $profile
        ]);
    }

    public function getAll(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $filters = [];

        $allowedParams = ['gender', 'country_id', 'age_group', 'min_age', 'max_age', 'min_gender_probability', 'min_country_probability', 'sort_by', 'order', 'page', 'limit'];
        foreach ($allowedParams as $param) {
            if (isset($queryParams[$param]) && $queryParams[$param] !== '') {
                $filters[$param] = $queryParams[$param];
            }
        }

        $result = $this->service->getAllProfiles($filters);

        return $this->json($response, 200, [
            'status' => 'success',
            'page'   => (int) ($filters['page'] ?? 1),
            'limit'  => (int) ($filters['limit'] ?? 10),
            'total'  => $result['total'],
            'data'   => $result['data']
        ]);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        $this->service->deleteProfile($id);

        return $response->withStatus(204);
    }

    private function json(ResponseInterface $response, int $status, array $data): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    public function search(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $q = trim($queryParams['q'] ?? '');

        if ($q === '') {
            return $this->json($response, 400, [
                'status' => 'error',
                'message' => 'Missing or empty query'
            ]);
        }

        $parser = new QueryParser();
        $filters = $parser->parse($q);

        if (isset($filters['_uninterpretable'])) {
            return $this->json($response, 400, [
                'status' => 'error',
                'message' => 'Unable to interpret query'
            ]);
        }

        // Merge pagination params if present
        foreach (['page', 'limit'] as $param) {
            if (isset($queryParams[$param]) && $queryParams[$param] !== '') {
                $filters[$param] = $queryParams[$param];
            }
        }

        $result = $this->service->getAllProfiles($filters);

        return $this->json($response, 200, [
            'status' => 'success',
            'page'   => (int) ($filters['page'] ?? 1),
            'limit'  => (int) ($filters['limit'] ?? 10),
            'total'  => $result['total'],
            'data'   => $result['data']
        ]);
    }
}
