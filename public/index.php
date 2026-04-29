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

$app->get('/', function ($req, $res) {
    $res->getBody()->write(json_encode([
        'status' => 'success',
        'service' => 'Insighta Labs+',
        'version' => '1.0.0',
    ]));
    return $res->withHeader('Content-Type', 'application/json');
});

$app->get('/health', function ($req, $res) {
    $res->getBody()->write(json_encode([
        'status' => 'success',
        'message' => 'OK',
    ]));
    return $res->withHeader('Content-Type', 'application/json');
});
$app->run();
