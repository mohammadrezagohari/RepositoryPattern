<?php

namespace Gohari\RepositoryPattern;

use Gohari\RepositoryPattern\Commands\MakeRepositoryCommand;
use Illuminate\Support\ServiceProvider;

class RepositoryPatternServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/repository-pattern.php', 'repository-pattern');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeRepositoryCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/repository-pattern.php' => config_path('repository-pattern.php'),
            ], 'repository-pattern-config');

            $this->publishes([
                __DIR__.'/stubs' => resource_path('stubs/vendor/repository-pattern'),
            ], 'repository-pattern-stubs');
        }
    }
}
