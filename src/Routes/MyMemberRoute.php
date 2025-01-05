<?php

namespace App\Routes;

use App\Controllers\MyMemberController;
use App\Middleware\AuthMiddleware;

class MyMemberRoute extends BaseRoute
{
    public function register(): void
    {
        $this->app->group('/v1/my-member', function ($group) {
            $group->get('/profile', [MyMemberController::class, 'myProfile']);
            $group->put('/avatar', [MyMemberController::class, 'updateAvatar']);
        })->add(new AuthMiddleware());
    }
}
