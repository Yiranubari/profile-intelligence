<?php

use App\Controllers\ProfileController;
use Slim\App;

return function (App $app) {
    $app->post('/api/profiles', [ProfileController::class, 'create']);
    $app->get('/api/profiles', [ProfileController::class, 'getAll']);
    $app->get('/api/profiles/{id}', [ProfileController::class, 'getOne']);
    $app->delete('/api/profiles/{id}', [ProfileController::class, 'delete']);
};
