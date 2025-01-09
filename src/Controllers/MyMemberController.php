<?php

namespace App\Controllers;

use Exception;
use App\Helpers\JWTHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Helpers\ResponseHandle;
use App\Helpers\VerifyUserStatus;
use App\Models\User;
use App\Models\UserInfo;
use App\Models\UserInfoTranslation;
use App\Models\UserRole;

class MyMemberController
{
    /**
     * GET /v1/my-member/profile
     */
    public function myProfile(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $token = $queryParams['token'] ?? '';

            $user = JWTHelper::getUser($token);
            if (!$user) {
                return ResponseHandle::error($response, 'Unauthorized', 401);
            }

            $statusCheckResponse = VerifyUserStatus::check($user->status_id, $response);
            if ($statusCheckResponse) {
                return $statusCheckResponse;
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
                    'avatar_base_url' => $userModel->avatar_base_url,
                    'avatar_lazy_url' => $userModel->avatar_lazy_url,
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

    /**
     * PUT /v1/my-member/avatar
     */
    public function updateAvatar(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $token = $queryParams['token'] ?? '';

            $user = JWTHelper::getUser($token);
            if (!$user) {
                return ResponseHandle::error($response, 'Unauthorized', 401);
            }

            $statusCheckResponse = VerifyUserStatus::check($user->status_id, $response);
            if ($statusCheckResponse) {
                return $statusCheckResponse;
            }

            $body = json_decode((string)$request->getBody(), true);
            $avatarId = $body['avatar_id'] ?? null;
            $avatarBaseUrl = $body['avatar_base_url'] ?? null;
            $avatarLazyUrl = $body['avatar_lazy_url'] ?? null;

            if (!$user || !$avatarId || !$avatarBaseUrl || !$avatarLazyUrl) {
                return ResponseHandle::error($response, 'All required fields must be provided', 400);
            }

            $userId = $user['user_id'];

            $user = User::where('user_id', $userId)->first();
            if (!$user) {
                return ResponseHandle::error($response, 'User not found', 404);
            }

            $user->avatar_id = $avatarId;
            $user->avatar_base_url = $avatarBaseUrl;
            $user->avatar_lazy_url = $avatarLazyUrl;
            $user->save();

            return ResponseHandle::success($response, [
                'avatar_base_url' => $avatarBaseUrl,
                'avatar_lazy_url' => $avatarLazyUrl,
            ], 'Avatar uploaded successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * PUT /v1/my-member/detail
     */
    public function updateUserDetail(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $token = $queryParams['token'] ?? '';

            $user = JWTHelper::getUser($token);
            if (!$user) {
                return ResponseHandle::error($response, 'Unauthorized', 401);
            }

            $statusCheckResponse = VerifyUserStatus::check($user->status_id, $response);
            if ($statusCheckResponse) {
                return $statusCheckResponse;
            }

            $body = json_decode((string)$request->getBody(), true);
            if (!is_array($body)) {
                return ResponseHandle::error($response, 'Invalid request body', 400);
            }

            $userId = $user['user_id'];

            if (isset($body['phone'])) {
                $userInfo = UserInfo::where('user_id', $userId)->first();
                if (!$userInfo) {
                    return ResponseHandle::error($response, 'UserInfo not found for this user', 404);
                }
                $userInfo->phone = $body['phone'];
                $userInfo->save();
            }

            if (isset($body['first_name_th']) || isset($body['last_name_th']) || isset($body['nickname_th'])) {
                $translationTh = UserInfoTranslation::where('user_id', $userId)
                    ->where('language_code', 'th')
                    ->first();
                if (!$translationTh) {
                    return ResponseHandle::error($response, 'Translation (TH) not found for this user', 404);
                }
                $translationTh->first_name = $body['first_name_th'] ?? $translationTh->first_name;
                $translationTh->last_name = $body['last_name_th'] ?? $translationTh->last_name;
                $translationTh->nickname = $body['nickname_th'] ?? $translationTh->nickname;
                $translationTh->save();
            }

            if (isset($body['first_name_en']) || isset($body['last_name_en']) || isset($body['nickname_en'])) {
                $translationEn = UserInfoTranslation::where('user_id', $userId)
                    ->where('language_code', 'en')
                    ->first();
                if (!$translationEn) {
                    return ResponseHandle::error($response, 'Translation (EN) not found for this user', 404);
                }
                $translationEn->first_name = $body['first_name_en'] ?? $translationEn->first_name;
                $translationEn->last_name = $body['last_name_en'] ?? $translationEn->last_name;
                $translationEn->nickname = $body['nickname_en'] ?? $translationEn->nickname;
                $translationEn->save();
            }

            if (isset($body['role_id'])) {
                $userRole = UserRole::where('user_id', $userId)->first();
                if (!$userRole) {
                    return ResponseHandle::error($response, 'UserRole not found for this user', 404);
                }
                $userRole->role_id = $body['role_id'];
                $userRole->save();
            }

            return ResponseHandle::success($response, [], 'User detail updated successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/my-member/reset-password
     */
    public function resetPassword(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $token = $queryParams['token'] ?? '';

            $user = JWTHelper::getUser($token);
            if (!$user) {
                return ResponseHandle::error($response, 'Unauthorized', 401);
            }

            $statusCheckResponse = VerifyUserStatus::check($user->status_id, $response);
            if ($statusCheckResponse) {
                return $statusCheckResponse;
            }

            $body = json_decode((string)$request->getBody(), true);
            $newPassword = $body['new_password'] ?? null;

            if (!$newPassword) {
                return ResponseHandle::error($response, 'User and New password are required', 400);
            }

            $user = User::where('user_id', $user['user_id'])->first();
            $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
            $user->save();

            return ResponseHandle::success($response, [], 'Password reset successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }
}
