<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;

trait JsonResponseTrait
{
    private function json(ResponseInterface $response, int $status, array $data): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}
