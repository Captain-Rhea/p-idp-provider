<?php

namespace App\Controllers;

use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Helpers\ResponseHandle;
use App\Models\Status;
use App\Models\User;

class UserController
{
    /**
     * GET /v1/user/me
     */
    public function me(Request $request, Response $response): Response
    {
        try {
            $user = $request->getAttribute('user');

            if (!$user) {
                return ResponseHandle::error($response, 'Unauthorized', 401);
            }

            $userModel = User::with(['userInfo', 'roles', 'permissions'])->find($user['user_id']);

            if (!$userModel) {
                return ResponseHandle::error($response, 'User not found', 404);
            }

            $userStatus = Status::where('id', $userModel->status)->first();

            $userData = [
                'user_id' => $userModel->user_id,
                'email' => $userModel->email,
                'status' => [
                    'id' => $userStatus['id'],
                    'name' => $userStatus['name']
                ],
                'user_info' => $userModel->userInfo ? [
                    'first_name' => $userModel->userInfo->first_name,
                    'last_name' => $userModel->userInfo->last_name,
                    'nickname' => $userModel->userInfo->nickname,
                    'phone' => $userModel->userInfo->phone,
                    'avatar_url' => $userModel->userInfo->avatar_url,
                ] : null,
                'roles' => $userModel->roles->map(function ($role) {
                    return [
                        'role_id' => $role->id,
                        'name' => $role->name,
                        'description' => $role->description,
                    ];
                })->toArray(),
                'permissions' => $userModel->permissions->map(function ($permission) {
                    return [
                        'permission_id' => $permission->id,
                        'name' => $permission->name,
                        'description' => $permission->description,
                    ];
                })->toArray(),
            ];

            return ResponseHandle::success($response, $userData, 'User data retrieved successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }
}
