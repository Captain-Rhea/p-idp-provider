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
            $group->get('/is-login', [AuthController::class, 'isLogin']);
            $group->get('/verify-token', [AuthController::class, 'verifyToken']);
            // Reset password
            $group->post('/reset-password', [AuthController::class, 'resetPassword']);
            $group->get('/forgot-password', [AuthController::class, 'getForgotPasswords']);
            $group->post('/send/forgot-mail', [AuthController::class, 'sendForgotMail']);
            $group->post('/send/forgot-mail/verify', [AuthController::class, 'forgotMailVerify']);
            $group->post('/send/forgot-mail/reset-password', [AuthController::class, 'forgotMailResetNewPassword']);
            // Transaction
            $group->get('/transaction/login', [AuthController::class, 'getLoginTransaction']);
        })->add(new AuthMiddleware());
    }
}
