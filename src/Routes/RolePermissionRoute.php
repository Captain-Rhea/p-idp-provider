<?php

namespace App\Routes;

use App\Controllers\RolePermissionController;
use App\Middleware\AuthMiddleware;

class RolePermissionRoute extends BaseRoute
{
    public function register(): void
    {
        $this->app->group('/v1', function ($group) {
            $group->get('/roles', [RolePermissionController::class, 'getRoles']);
            $group->get('/permissions', [RolePermissionController::class, 'getPermissions']);
        })->add(new AuthMiddleware());
    }
}
