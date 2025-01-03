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
            $group->post('/invite', [MemberController::class, 'createInvitation'])->add(new AuthMiddleware());
            $group->put('/invite/reject/{id}', [MemberController::class, 'rejectInvitation'])->add(new AuthMiddleware());
            $group->post('/invite/verify', [MemberController::class, 'verifyInvitation']);
            $group->post('/invite/accept', [MemberController::class, 'acceptInvitation']);

            $group->get('', [MemberController::class, 'getMembers'])->add(new AuthMiddleware());
            $group->post('', [MemberController::class, 'createMember'])->add(new AuthMiddleware());
            $group->delete('/{id}', [MemberController::class, 'permanentlyDeleteMember'])->add(new AuthMiddleware());
            $group->delete('/{id}/soft', [MemberController::class, 'softDeleteMember'])->add(new AuthMiddleware());
            $group->put('/suspend/{id}', [MemberController::class, 'suspendMember'])->add(new AuthMiddleware());
            $group->put('/active/{id}', [MemberController::class, 'activeMember'])->add(new AuthMiddleware());
        });
    }
}
