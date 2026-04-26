<?php

use App\Controllers\AuthController;
use Slim\App;

return function (App $app) {
    $app->get('/auth/github', [AuthController::class, 'redirectToGithub']);
    $app->get('/auth/github/callback', [AuthController::class, 'handleCallback']);
    $app->post('/auth/cli/exchange', [AuthController::class, 'exchangeCli']);
    $app->post('/auth/refresh', [AuthController::class, 'refresh']);
    $app->post('/auth/logout', [AuthController::class, 'logout']);
    $app->get('/auth/me', [AuthController::class, 'me']);
};
