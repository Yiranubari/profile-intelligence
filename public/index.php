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

// Middleware — order matters, outer to inner
$app->add(JsonBodyParserMiddleware::class);
$app->add(ErrorHandlerMiddleware::class);
$app->add(LoggerMiddleware::class);
$app->add(CorsMiddleware::class);

// Routes
$routes = require __DIR__ . '/../src/Routes/api.php';
$routes($app);

$app->run();
