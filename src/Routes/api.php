<?php

use App\Controllers\ProfileController;
use App\Controllers\UserController;
use App\Middleware\ApiVersionMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;


return function (App $app) {
    $app->group('/api', function (RouteCollectorProxy $group) {
        // Profiles
        $group->get('/profiles', [ProfileController::class, 'getAll']);
        $group->get('/profiles/search', [ProfileController::class, 'search']);
        $group->get('/profiles/export', [ProfileController::class, 'export']);
        $group->post('/profiles/upload', [ProfileController::class, 'upload'])
            ->add('role.admin');
        $group->get('/profiles/{id}', [ProfileController::class, 'getOne']);
        $group->post('/profiles', [ProfileController::class, 'create'])
            ->add('role.admin');
        $group->delete('/profiles/{id}', [ProfileController::class, 'delete'])
            ->add('role.admin');

        // Users (admin only)
        $group->get('/users/me', [\App\Controllers\AuthController::class, 'me']);
        $group->get('/users', [UserController::class, 'listAll'])
            ->add('role.admin');
        $group->patch('/users/{id}/role', [UserController::class, 'updateRole'])
            ->add('role.admin');
    })
        ->add(CsrfMiddleware::class)
        ->add('rate.api')
        ->add(AuthMiddleware::class)
        ->add(ApiVersionMiddleware::class);
};
