<?php

namespace App\Providers;

use App\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Session::extend('database', function ($app) {
            $config = $app['config']['session'];

            return new DatabaseSessionHandler(
                $app['db']->connection($config['connection'] ?? null),
                $config['table'],
                $config['lifetime'],
                $app
            );
        });
    }
}
