<?php

namespace App\Routes;

use App\Controllers\EmailController;
use App\Middleware\AuthMiddleware;

class EmailRoute extends BaseRoute
{
    public function register(): void
    {
        $this->app->group('/v1/email', function ($group) {
            $group->post('/send/verify', [EmailController::class, 'sendVerifyEmail']);
            $group->post('/send/reset', [EmailController::class, 'sendResetPasswordEmail']);
            $group->post('/send/invite', [EmailController::class, 'sendInviteEmail'])->add(new AuthMiddleware());
        });
    }
}
