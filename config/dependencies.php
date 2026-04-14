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

]);

return $containerBuilder->build();
