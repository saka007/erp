<?php

namespace DigitalFuzed\TextileInventory\Providers;

use DigitalFuzed\TextileInventory\Services\TextileLotAutoCreationService;
use Illuminate\Support\ServiceProvider;

class TextileInventoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $routesPath = __DIR__.'/../Routes/web.php';
        if (file_exists($routesPath)) {
            $this->loadRoutesFrom($routesPath);
        }

        $apiRoutesPath = __DIR__.'/../Routes/api.php';
        if (file_exists($apiRoutesPath)) {
            $this->loadRoutesFrom($apiRoutesPath);
        }

        $migrationsPath = __DIR__.'/../Database/Migrations';
        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }
    }

    public function register(): void
    {
        $this->app->singleton(TextileLotAutoCreationService::class, function () {
            return new TextileLotAutoCreationService();
        });
    }
}
