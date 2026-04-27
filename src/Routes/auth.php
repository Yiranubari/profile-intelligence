<?php

use App\Controllers\AuthController;
use App\Middleware\AuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app) {
    $app->group('/auth', function (RouteCollectorProxy $group) {
        $group->get('/github', [AuthController::class, 'redirectToGithub']);
        $group->get('/github/callback', [AuthController::class, 'handleCallback']);
        $group->post('/cli/exchange', [AuthController::class, 'exchangeCli']);
        $group->post('/refresh', [AuthController::class, 'refresh']);
        $group->post('/logout', [AuthController::class, 'logout']);
        $group->get('/me', [AuthController::class, 'me'])->add(AuthMiddleware::class);
    })->add('rate.auth');
};
