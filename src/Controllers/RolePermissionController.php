<?php

namespace App\Controllers;

use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Helpers\ResponseHandle;
use App\Models\Permission;
use App\Models\Role;

class RolePermissionController
{
    // GET /v1/role
    public function getRoles(Request $request, Response $response): Response
    {
        try {
            $roles = Role::all();
            return ResponseHandle::success($response, $roles, 'Roles list retrieved successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    // GET /v1/permission
    public function getPermissions(Request $request, Response $response): Response
    {
        try {
            $roles = Permission::all();
            return ResponseHandle::success($response, $roles, 'Permission list retrieved successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }
}
