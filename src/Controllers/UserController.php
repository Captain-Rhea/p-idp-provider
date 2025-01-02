<?php

namespace App\Controllers;

use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Helpers\ResponseHandle;
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

            $userModel = User::with([
                'status',
                'userInfo',
                'userInfoTranslation',
                'roles',
                'permissions'
            ])->find($user['user_id']);

            if (!$userModel) {
                return ResponseHandle::error($response, 'User not found', 404);
            }

            $userData = [
                'user_id' => $userModel->user_id,
                'email' => $userModel->email,
                'updated_at' => $userModel->updated_at,
                'status' => [
                    'id' => $userModel->status->id,
                    'name' => $userModel->status->name
                ],
                'user_info' => $userModel->userInfo ? [
                    'phone' => $userModel->userInfo->phone,
                    'avatar_id' => $userModel->avatar_id,
                    'avatar_url' => $userModel->avatar_url,
                ] : null,
                'user_info_translation' => $userModel->userInfoTranslation->map(function ($translation) {
                    return [
                        'language_code' => $translation->language_code,
                        'first_name' => $translation->first_name,
                        'last_name' => $translation->last_name,
                        'nickname' => $translation->nickname,
                        'updated_at' => $translation->updated_at,
                    ];
                })->toArray(),
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
