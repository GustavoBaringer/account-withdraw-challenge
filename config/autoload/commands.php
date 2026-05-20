<?php

declare(strict_types=1);

return [
    Hyperf\Database\Commands\Migrations\MigrateCommand::class,
    Hyperf\Database\Commands\Migrations\FreshCommand::class,
    Hyperf\Database\Commands\Migrations\RefreshCommand::class,
    Hyperf\Database\Commands\Migrations\ResetCommand::class,
    Hyperf\Database\Commands\Migrations\RollbackCommand::class,
    Hyperf\Database\Commands\Migrations\StatusCommand::class,
    Hyperf\Database\Commands\Migrations\GenMigrateCommand::class,
    Hyperf\Database\Commands\Migrations\InstallCommand::class,
    Hyperf\Database\Commands\Seeders\SeedCommand::class,
    Hyperf\Database\Commands\Seeders\GenSeederCommand::class,
];
