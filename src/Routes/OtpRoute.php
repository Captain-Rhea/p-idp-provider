<?php

namespace App\Routes;

use App\Controllers\OtpController;
use App\Middleware\AuthMiddleware;

class OtpRoute extends BaseRoute
{
    public function register(): void
    {
        $this->app->group('/v1/otp', function ($group) {
            $group->get('', [OtpController::class, 'getAll']);
            $group->post('', [OtpController::class, 'sendOTP']);
            $group->post('/verify', [OtpController::class, 'verify']);
        })->add(new AuthMiddleware());
    }
}
