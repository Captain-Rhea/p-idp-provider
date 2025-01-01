<?php

namespace App\Routes;

use App\Controllers\MemberController;
use App\Middleware\AuthMiddleware;

class MemberRoute extends BaseRoute
{
    public function register(): void
    {
        $this->app->group('/v1/member', function ($group) {
            $group->post('/send/invite', [MemberController::class, 'createInvitation'])->add(new AuthMiddleware());
        });
    }
}
