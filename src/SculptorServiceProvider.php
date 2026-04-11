<?php

namespace Atldays\Sculptor;

use Illuminate\Support\ServiceProvider;

class SculptorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/sculptor.php', 'sculptor'
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/sculptor.php' => $this->app->configPath('sculptor.php'),
            ], 'config');

            $this->commands([
                Console\Commands\FlushCacheCommand::class,
            ]);
        }
    }
}
