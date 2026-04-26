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

        // Validate numeric params
        $numericParams = ['min_age', 'max_age', 'page', 'limit'];
        foreach ($numericParams as $param) {
            if (isset($filters[$param]) && !is_numeric($filters[$param])) {
                return $this->json($response, 400, [
                    'status' => 'error',
                    'message' => 'Invalid query parameters'
                ]);
            }
        }

        // Normalize sort params before validation
        if (isset($filters['sort_by'])) {
            $filters['sort_by'] = strtolower(trim($filters['sort_by']));
        }
        if (isset($filters['order'])) {
            $filters['order'] = strtolower(trim($filters['order']));
        }

        // Validate sort_by
        if (isset($filters['sort_by']) && !in_array($filters['sort_by'], ['age', 'created_at', 'gender_probability'], true)) {
            return $this->json($response, 400, [
                'status' => 'error',
                'message' => 'Invalid query parameters'
            ]);
        }

        // Validate order
        if (isset($filters['order']) && !in_array($filters['order'], ['asc', 'desc'], true)) {
            return $this->json($response, 400, [
                'status' => 'error',
                'message' => 'Invalid query parameters'
            ]);
        }

        // Validate probability ranges
        $probabilityParams = ['min_gender_probability', 'min_country_probability'];
        foreach ($probabilityParams as $param) {
            if (isset($filters[$param])) {
                $val = (float) $filters[$param];
                if (!is_numeric($filters[$param]) || $val < 0 || $val > 1) {
                    return $this->json($response, 400, [
                        'status' => 'error',
                        'message' => 'Invalid query parameters'
                    ]);
                }
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

        // Merge pagination and sort params if present
        foreach (['page', 'limit', 'sort_by', 'order'] as $param) {
            if (isset($queryParams[$param]) && $queryParams[$param] !== '') {
                $filters[$param] = $queryParams[$param];
            }
        }

        // Normalize sort params before validation
        if (isset($filters['sort_by'])) {
            $filters['sort_by'] = strtolower(trim($filters['sort_by']));
        }
        if (isset($filters['order'])) {
            $filters['order'] = strtolower(trim($filters['order']));
        }

        // Validate sort params
        if (isset($filters['sort_by']) && !in_array($filters['sort_by'], ['age', 'created_at', 'gender_probability'], true)) {
            return $this->json($response, 400, [
                'status' => 'error',
                'message' => 'Invalid query parameters'
            ]);
        }

        if (isset($filters['order']) && !in_array($filters['order'], ['asc', 'desc'], true)) {
            return $this->json($response, 400, [
                'status' => 'error',
                'message' => 'Invalid query parameters'
            ]);
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
