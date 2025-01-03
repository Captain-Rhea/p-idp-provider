<?php

namespace App\Routes;

use App\Controllers\MemberController;
use App\Middleware\AuthMiddleware;

class MemberRoute extends BaseRoute
{
    public function register(): void
    {
        $this->app->group('/v1/member', function ($group) {
            $group->get('/invite', [MemberController::class, 'getInvitation'])->add(new AuthMiddleware());
            $group->post('/create', [MemberController::class, 'createMember'])->add(new AuthMiddleware());
            $group->post('/send/invite', [MemberController::class, 'createInvitation'])->add(new AuthMiddleware());
            $group->put('/invite/reject/{id}', [MemberController::class, 'rejectInvitation'])->add(new AuthMiddleware());
            $group->post('/invite/verify', [MemberController::class, 'verifyInvitation']);
            $group->post('/invite/accept', [MemberController::class, 'acceptInvitation']);
        });
    }
}
