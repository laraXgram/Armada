<?php

namespace LaraGram\Armada;

use LaraGram\Armada\Console\AddCommand;
use LaraGram\Armada\Console\InstallCommand;
use LaraGram\Armada\Console\ProductionCommand;
use LaraGram\Armada\Console\PublishCommand;
use LaraGram\Contracts\Support\DeferrableProvider;
use LaraGram\Support\ServiceProvider;

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
                ProductionCommand::class,
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
                __DIR__.'/../runtimes/app' => $this->app->basePath('docker/app'),
                __DIR__.'/../runtimes/telegram-bot-api' => $this->app->basePath('docker/telegram-bot-api'),
                __DIR__.'/../runtimes/apiserver' => $this->app->basePath('docker/apiserver'),
            ], ['armada', 'armada-docker']);

            $this->publishes([
                __DIR__.'/../bin/armada' => $this->app->basePath('armada'),
            ], ['armada', 'armada-bin']);

            $this->publishes([
                __DIR__.'/../database' => $this->app->basePath('docker'),
            ], ['armada', 'armada-database']);

            $this->publishes([
                __DIR__.'/../runtimes/production' => $this->app->basePath('docker/production'),
                __DIR__.'/../runtimes/telegram-bot-api' => $this->app->basePath('docker/telegram-bot-api'),
                __DIR__.'/../runtimes/apiserver' => $this->app->basePath('docker/apiserver'),
                __DIR__.'/../stubs/dockerignore.stub' => $this->app->basePath('.dockerignore'),
            ], 'armada-production');
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
            AddCommand::class,
            PublishCommand::class,
            ProductionCommand::class,
        ];
    }
}
