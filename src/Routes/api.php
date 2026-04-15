<?php

use App\Controllers\ProfileController;
use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

return function (App $app) {
    // Health check route
    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Profile Intelligence API is running'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->post('/api/profiles', [ProfileController::class, 'create']);
    $app->get('/api/profiles', [ProfileController::class, 'getAll']);
    $app->get('/api/profiles/{id}', [ProfileController::class, 'getOne']);
    $app->delete('/api/profiles/{id}', [ProfileController::class, 'delete']);
};
