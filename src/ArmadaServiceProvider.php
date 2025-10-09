<?php

namespace LaraGram\Armada;

use LaraGram\Contracts\Support\DeferrableProvider;
use LaraGram\Support\ServiceProvider;
use LaraGram\Armada\Console\AddCommand;
use LaraGram\Armada\Console\InstallCommand;
use LaraGram\Armada\Console\PublishCommand;

class ArmadaServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerCommands();
        $this->configurePublishing();
    }

    /**
     * Register the console commands for the package.
     *
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                AddCommand::class,
                PublishCommand::class,
            ]);
        }
    }

    /**
     * Configure publishing for the package.
     *
     * @return void
     */
    protected function configurePublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../runtimes' => $this->app->basePath('docker'),
            ], ['armada', 'armada-docker']);

            $this->publishes([
                __DIR__ . '/../bin/armada' => $this->app->basePath('armada'),
            ], ['armada', 'armada-bin']);

            $this->publishes([
                __DIR__ . '/../database' => $this->app->basePath('docker'),
            ], ['armada', 'armada-database']);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            InstallCommand::class,
            PublishCommand::class,
        ];
    }
}
