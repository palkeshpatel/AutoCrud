<?php

namespace Palkesh\AutoCrud;

use Illuminate\Support\ServiceProvider;
use Palkesh\AutoCrud\Commands\GenerateCrudCommand;

class AutoCrudServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateCrudCommand::class,
            ]);
        }
    }
}
