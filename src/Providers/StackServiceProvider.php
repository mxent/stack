<?php

namespace Mxent\Stack\Providers;

use Illuminate\Support\ServiceProvider;

class StackServiceProvider extends ServiceProvider
{
    /**
     * Register
     */
    public function register()
    {
        $this->registerCommands();
    }

    /**
     * Register Commands
     */
    protected function registerCommands()
    {
        $this->commands([
            \Mxent\Stack\Commands\InitCommand::class,
        ]);
    }

    /**
     * Register helpers.php
     */
    protected function registerHelpers()
    {
        require_once __DIR__.'/../helpers.php';
    }
}
