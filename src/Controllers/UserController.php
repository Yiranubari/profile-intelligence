<?php

namespace App\Controllers;

use App\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UserController
{
    public function __construct(
        private UserRepository $users
    ) {}

    public function listAll(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $users = $this->users->findAll();
        return $this->json($response, 200, [
            'status' => 'success',
            'data' => $users,
        ]);
    }

    public function updateRole(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        $body = $request->getParsedBody() ?? [];
        $role = $body['role'] ?? null;

        if (!in_array($role, ['admin', 'analyst'], true)) {
            return $this->json($response, 400, [
                'status' => 'error',
                'message' => 'Invalid role',
            ]);
        }

        $currentUser = $request->getAttribute('user');
        if ($currentUser['id'] === $id && $role === 'analyst') {
            return $this->json($response, 400, [
                'status' => 'error',
                'message' => 'Cannot demote yourself',
            ]);
        }

        $existing = $this->users->findById($id);
        if ($existing === null) {
            return $this->json($response, 404, [
                'status' => 'error',
                'message' => 'User not found',
            ]);
        }

        $updated = $this->users->updateRole($id, $role);

        return $this->json($response, 200, [
            'status' => 'success',
            'data' => $updated,
        ]);
    }

    private function json(ResponseInterface $response, int $status, array $data): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
