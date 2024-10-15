<?php


namespace Laravel\Foundation\Abstracts;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

abstract class AbstractAppServiceProvider extends ServiceProvider
{
    protected array $modelList = [];
    protected array $namespacedModelList = [];

    protected array $serviceList = [];

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerRepositories();
        $this->registerNamespacedRepositories();
        $this->registerServices();
    }

    private function registerRepositories()
    {
        foreach ($this->modelList as $modelClass) {
            $modelName = Str::afterLast($modelClass, '\\');
            $repositoryClass = "App\\Repositories\\{$modelName}Repository";
            $this->app->singleton($repositoryClass, function () use ($modelClass, $repositoryClass) {
                return new $repositoryClass(new $modelClass());
            });
        }
    }

    private function registerNamespacedRepositories()
    {
        foreach ($this->namespacedModelList as $namespace => $modelClasses) {
            $namespace = Str::endsWith($namespace, '\\') ? $namespace : "$namespace\\";
            foreach ($modelClasses as $modelClass) {
                $modelName = Str::afterLast($modelClass, '\\');
                $repositoryClass = "$namespace{$modelName}Repository";
                $this->app->singleton($repositoryClass, function () use ($modelClass, $repositoryClass) {
                    return new $repositoryClass(new $modelClass());
                });
            }
        }
    }

    private function registerServices()
    {
        foreach ($this->serviceList as $serviceClass) {
            $this->app->singleton($serviceClass, function () use ($serviceClass) {
                return new $serviceClass();
            });
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}
