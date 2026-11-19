<?php

namespace LaravelStisla\Stisla;

use Illuminate\Support\ServiceProvider;
use LaravelStisla\Stisla\Console\InstallCommand;

class StislaServiceProvider extends ServiceProvider
{
    /**
     * Register package services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap package services and register publishable stubs.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InstallCommand::class,
        ]);

        $stubsPath = dirname(__DIR__).'/stubs';

        $publishGroups = [
            'stisla-controllers' => [$stubsPath.'/app/Http/Controllers' => app_path('Http/Controllers')],
            'stisla-models' => [$stubsPath.'/app/Models' => app_path('Models')],
            'stisla-commands' => [$stubsPath.'/app/Console/Commands' => app_path('Console/Commands')],
            'stisla-exports' => [$stubsPath.'/app/Exports' => app_path('Exports')],
            'stisla-middleware' => [$stubsPath.'/app/Http/Middleware' => app_path('Http/Middleware')],
            'stisla-requests' => [$stubsPath.'/app/Http/Requests' => app_path('Http/Requests')],
            'stisla-imports' => [$stubsPath.'/app/Imports' => app_path('Imports')],
            'stisla-mail' => [$stubsPath.'/app/Mail' => app_path('Mail')],
            'stisla-services' => [$stubsPath.'/app/Services' => app_path('Services')],
            'stisla-config' => [$stubsPath.'/config' => config_path()],
            'stisla-views' => [$stubsPath.'/resources/views' => resource_path('views')],
            'stisla-public' => [$stubsPath.'/public' => public_path()],
            'stisla-routes' => [$stubsPath.'/routes' => base_path('routes')],
            'stisla-database' => [$stubsPath.'/database' => database_path()],
            'stisla-lang' => [$stubsPath.'/lang' => lang_path()],
        ];

        $all = [];
        foreach ($publishGroups as $tag => $paths) {
            $this->publishes($paths, $tag);
            $all = array_merge($all, $paths);
        }

        $this->publishes($all, 'stisla-all');
    }
}
