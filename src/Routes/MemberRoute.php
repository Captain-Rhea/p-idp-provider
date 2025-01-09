<?php

namespace App\Routes;

use App\Controllers\MemberController;
use App\Middleware\AuthMiddleware;

class MemberRoute extends BaseRoute
{
    public function register(): void
    {
        $this->app->group('/v1/member', function ($group) {
            $group->get('/invite', [MemberController::class, 'getInvitation']);
            $group->post('/invite', [MemberController::class, 'createInvitation']);
            $group->put('/invite/reject/{id}', [MemberController::class, 'rejectInvitation']);
            $group->post('/invite/verify', [MemberController::class, 'verifyInvitation']);
            $group->post('/invite/accept', [MemberController::class, 'acceptInvitation']);

            $group->get('', [MemberController::class, 'getMembers']);
            $group->post('', [MemberController::class, 'createMember']);
            $group->delete('/{id}', [MemberController::class, 'permanentlyDeleteMember']);
            $group->delete('/{id}/soft', [MemberController::class, 'softDeleteMember']);
            $group->put('/suspend/{id}', [MemberController::class, 'suspendMember']);
            $group->put('/active/{id}', [MemberController::class, 'activeMember']);
            $group->put('/change-role/{user_id}', [MemberController::class, 'changeRoleMember']);
        })->add(new AuthMiddleware());
    }
}
