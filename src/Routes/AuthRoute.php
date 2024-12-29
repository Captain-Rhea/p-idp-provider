<?php

namespace App\Routes;

use App\Controllers\AuthController;
use App\Middleware\AuthMiddleware;

class AuthRoute extends BaseRoute
{
    public function register(): void
    {
        $this->app->group('/v1/auth', function ($group) {
            $group->post('/login', [AuthController::class, 'login']);
            $group->post('/register', [AuthController::class, 'register']);
            $group->post('/email-checking', [AuthController::class, 'emailChecking']);
            $group->post('/nickname-checking', [AuthController::class, 'nicknameChecking']);
        });
    }
}
