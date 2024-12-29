<?php

namespace App\Routes;

use Slim\App;

abstract class BaseRoute
{
    protected App $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    abstract public function register(): void;
}
