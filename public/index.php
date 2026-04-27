<?php

use App\Middleware\CorsMiddleware;
use App\Middleware\ErrorHandlerMiddleware;
use App\Middleware\JsonBodyParserMiddleware;
use App\Middleware\LoggerMiddleware;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/app.php';

$container = require __DIR__ . '/../config/dependencies.php';

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->add(JsonBodyParserMiddleware::class);
$app->add(ErrorHandlerMiddleware::class);
$app->add(LoggerMiddleware::class);
$app->add(CorsMiddleware::class);

// Routes
$routes = require __DIR__ . '/../src/Routes/api.php';
$routes($app);

$authRoutes = require __DIR__ . '/../src/Routes/auth.php';
$authRoutes($app);

$app->get('/_debug', function ($req, $res) {
    $res->getBody()->write(json_encode([
        'env' => array_keys($_ENV),
        'jwt_present' => !empty($_ENV['JWT_SECRET']),
        'getenv_jwt' => !empty(getenv('JWT_SECRET')),
        'github_id_present' => !empty($_ENV['GITHUB_CLIENT_ID']),
    ]));

    return $res->withHeader('Content-Type', 'application/json');
});

$app->get('/_debug2', function ($req, $res) use ($container) {
    try {
        $authService = $container->get(\App\Services\AuthService::class);
        $url = $authService->startOAuthFlow('web');
        $res->getBody()->write(json_encode(['ok' => true, 'url' => $url]));
    } catch (\Throwable $e) {
        $res->getBody()->write(json_encode([
            'ok' => false,
            'error' => $e->getMessage(),
            'class' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 8),
        ]));
    }

    return $res->withHeader('Content-Type', 'application/json');
});

$app->get('/_oauth_test', function ($req, $res) use ($container) {
    try {
        $authService = $container->get(\App\Services\AuthService::class);
        $url = $authService->startOAuthFlow('web');
        $res->getBody()->write(json_encode(['ok' => true, 'url' => $url]));
    } catch (\Throwable $e) {
        $res->getBody()->write(json_encode([
            'ok' => false,
            'error' => $e->getMessage(),
            'class' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]));
    }

    return $res->withHeader('Content-Type', 'application/json');
});

$app->run();
