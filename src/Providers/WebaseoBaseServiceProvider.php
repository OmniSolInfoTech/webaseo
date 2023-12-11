<?php

namespace Osit\Webaseo\Providers;

use Illuminate\Support\ServiceProvider;
use Osit\Webaseo\Console;


/**
 * WebaseoBaseServiceProvider - main class
 *
 * WebaseoBaseServiceProvider
 * distributed under the MIT License
 *
 * @author  Dominic Moeketsi developer@osit.co.za
 * @company OmniSol Information Technology (PTY) LTD
 * @version 1.0.0
 */
class WebaseoBaseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot(): void
    {
        if($this->app->runningInConsole()) {
            $this->registerPublishing();
        }

        $this->registerResources();
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webaseo');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->commands([
            Console\ProcessCommand::class,
        ]);
    }

    /**
     * Register the package resources.
     *
     * @return void
     */
    private function registerResources(): void
    {

    }

    /**
     * Register the package's publishable resources.
     *
     * @return void
     */
    protected function registerPublishing(): void
    {
        $this->publishes([__DIR__."/../config/webaseo.php" => config_path("webaseo.php")], "webaseo-config");
        $this->publishes([ __DIR__."/../resources/webaseo-assets" => public_path("webaseo-assets"),], "webaseo-config");
    }
}