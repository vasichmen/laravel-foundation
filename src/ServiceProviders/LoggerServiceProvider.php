<?php


namespace Laravel\Foundation\ServiceProviders;

use Illuminate\Support\ServiceProvider;
use Laravel\Foundation\Logger\Handler\GraylogHandler;

class LoggerServiceProvider extends ServiceProvider
{
    public function boot()
    {
    }

    public function register()
    {
        $this->app->bind(GraylogHandler::class, function ($app, $config) {
            return new GraylogHandler($config);
        });
    }

    public function provides()
    {
        return [
            GraylogHandler::class,
        ];
    }

}
