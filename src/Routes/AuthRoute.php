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
            $group->post('/verify-token', [AuthController::class, 'verifyToken']);
            // Reset password
            $group->post('/reset-password', [AuthController::class, 'resetPassword'])->add(new AuthMiddleware());
            $group->post('/send/forgot-mail', [AuthController::class, 'sendForgotMail']);
            $group->post('/send/forgot-mail/verify', [AuthController::class, 'forgotMailVerify']);
            $group->post('/send/forgot-mail/reset-password', [AuthController::class, 'forgotMailResetNewPassword']);
        });
    }
}
