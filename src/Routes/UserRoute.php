<?php

namespace App\Routes;

use App\Controllers\UserController;
use App\Middleware\AuthMiddleware;

class UserRoute extends BaseRoute
{
    public function register(): void
    {
        $this->app->group('/v1/user', function ($group) {
            $group->get('/me', [UserController::class, 'me']);
        })->add(new AuthMiddleware());
    }
}
