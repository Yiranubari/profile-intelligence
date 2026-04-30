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

    $app->get('/seed-test-users-once', function ($req, $res) {
        if (getenv('ALLOW_TEST_CODE') !== 'true') {
            $res->getBody()->write(json_encode(['status' => 'error', 'message' => 'disabled']));
            return $res->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        require __DIR__ . '/../../scripts/seed_test_users.php';
        $res->getBody()->write(json_encode(['status' => 'success', 'message' => 'seeded']));
        return $res->withHeader('Content-Type', 'application/json');
    });
};
