<?php

use App\Database\Database;
use App\Repositories\ProfileRepository;
use App\Services\ExternalApiService;
use App\Services\ProfileService;
use App\Controllers\ProfileController;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use DI\ContainerBuilder;

$containerBuilder = new ContainerBuilder();

$containerBuilder->addDefinitions([

    // Logger
    LoggerInterface::class => function () {
        $logger = new Logger('profile-intelligence');
        $logger->pushHandler(new StreamHandler(
            __DIR__ . '/../logs/app.log',
            Level::Debug
        ));
        return $logger;
    },

    // Database
    Database::class => function () {
        return Database::getInstance();
    },

    // Repository
    ProfileRepository::class => function (ContainerInterface $container) {
        return new ProfileRepository(
            $container->get(Database::class)->getConnection()
        );
    },

    // External API Service
    ExternalApiService::class => function (ContainerInterface $container) {
        return new ExternalApiService(
            $container->get(LoggerInterface::class)
        );
    },

    // Profile Service
    ProfileService::class => function (ContainerInterface $container) {
        return new ProfileService(
            $container->get(ProfileRepository::class),
            $container->get(ExternalApiService::class)
        );
    },

    // Controller
    ProfileController::class => function (ContainerInterface $container) {
        return new ProfileController(
            $container->get(ProfileService::class),
            $container->get(LoggerInterface::class)
        );
    },



    // Auth-related services
    \App\Services\TokenService::class => function () {
        return new \App\Services\TokenService(
            $_ENV['JWT_SECRET'],
            (int) ($_ENV['ACCESS_TOKEN_TTL'] ?? 180),
            (int) ($_ENV['REFRESH_TOKEN_TTL'] ?? 300)
        );
    },

    \App\Repositories\UserRepository::class => function (\Psr\Container\ContainerInterface $c) {
        return new \App\Repositories\UserRepository(
            $c->get(\App\Database\Database::class)->getConnection()
        );
    },

    \App\Repositories\RefreshTokenRepository::class => function (\Psr\Container\ContainerInterface $c) {
        return new \App\Repositories\RefreshTokenRepository(
            $c->get(\App\Database\Database::class)->getConnection()
        );
    },

    \GuzzleHttp\Client::class => function () {
        return new \GuzzleHttp\Client([
            'timeout' => 10,
            'http_errors' => true,
        ]);
    },

    \App\Services\AuthService::class => function (\Psr\Container\ContainerInterface $c) {
        return new \App\Services\AuthService(
            $c->get(\App\Repositories\UserRepository::class),
            $c->get(\App\Repositories\RefreshTokenRepository::class),
            $c->get(\App\Services\TokenService::class),
            $c->get(\App\Database\Database::class)->getConnection(),
            $c->get(\Psr\Log\LoggerInterface::class),
            $c->get(\GuzzleHttp\Client::class),
            $_ENV['GITHUB_CLIENT_ID'],
            $_ENV['GITHUB_CLIENT_SECRET'],
            $_ENV['GITHUB_REDIRECT_URI'],
            (int) ($_ENV['REFRESH_TOKEN_TTL'] ?? 300)
        );
    },

    \App\Controllers\AuthController::class => function (\Psr\Container\ContainerInterface $c) {
        return new \App\Controllers\AuthController(
            $c->get(\App\Services\AuthService::class),
            $c->get(\Psr\Log\LoggerInterface::class),
            $_ENV['WEB_PORTAL_URL'] ?? 'http://localhost:3000',
            filter_var($_ENV['COOKIE_SECURE'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
            (int) ($_ENV['ACCESS_TOKEN_TTL'] ?? 180),
            (int) ($_ENV['REFRESH_TOKEN_TTL'] ?? 300)
        );
    },

    \App\Middleware\AuthMiddleware::class => function (\Psr\Container\ContainerInterface $c) {
        return new \App\Middleware\AuthMiddleware(
            $c->get(\App\Services\TokenService::class),
            $c->get(\App\Repositories\UserRepository::class)
        );
    },

    // RoleMiddleware is parameterized and needs named instances for route usage.
    'role.admin' => function () {
        return new \App\Middleware\RoleMiddleware('admin');
    },

    'role.analyst' => function () {
        return new \App\Middleware\RoleMiddleware('analyst');
    },

]);

return $containerBuilder->build();
