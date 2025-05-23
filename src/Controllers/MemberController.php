<?php

namespace App\Controllers;

use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Carbon;
use App\Models\InviteMember;
use App\Helpers\ResponseHandle;
use App\Models\Role;
use App\Models\User;
use App\Models\Permission;
use App\Models\UserInfo;
use App\Models\UserInfoTranslation;
use App\Models\UserPermission;
use App\Models\UserRole;
use Illuminate\Database\Capsule\Manager as DB;

class MemberController
{
    /**
     * GET /v1/member/invite
     */
    public function getInvitation(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $recipientEmail = $queryParams['recipient_email'] ?? null;
            $statusIds = $queryParams['status_id'] ?? null;
            $startDate = $queryParams['start_date'] ?? null;
            $endDate = $queryParams['end_date'] ?? null;
            $page = $queryParams['page'] ?? 1;
            $perPage = $queryParams['per_page'] ?? 10;

            $query = InviteMember::with(['inviter', 'status']);

            // Apply filters
            if ($recipientEmail) {
                $query->where('recipient_email', 'LIKE', '%' . $recipientEmail . '%');
            }

            if ($statusIds) {
                $statusIds = is_array($statusIds) ? $statusIds : explode(',', $statusIds);
                $query->whereIn('status_id', $statusIds);
            }

            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            } elseif ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            } elseif ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            $invites = $query->orderBy('expires_at', 'asc')->paginate($perPage, ['*'], 'page', $page);

            $roles = Role::all()->keyBy('id');

            $formattedData = collect($invites->items())->map(function ($invite) use ($roles) {
                $role = $roles->get($invite->role_id);
                return [
                    'id' => $invite->id,
                    'recipient_email' => $invite->recipient_email,
                    'domain' => $invite->domain,
                    'path' => $invite->path,
                    'ref_code' => $invite->ref_code,
                    'invite_link' => $invite->domain . '/' . $invite->path . '?ref_code=' . $invite->ref_code,
                    'status' => [
                        'id' => $invite->status->id,
                        'name' => $invite->status->name,
                        'description' => $invite->status->description,
                    ],
                    'role' => $role ? [
                        'id' => $role->id,
                        'name' => $role->name,
                        'description' => $role->description,
                    ] : null,
                    'inviter' => [
                        'user_id' => $invite->inviter->user_id,
                        'email' => $invite->inviter->email,
                        'avatar_base_url' => $invite->inviter->avatar_base_url,
                        'avatar_lazy_url' => $invite->inviter->avatar_lazy_url,
                    ],
                    'expires_at' => $invite->expires_at,
                    'created_at' => $invite->created_at,
                    'updated_at' => $invite->updated_at,
                ];
            });

            return ResponseHandle::success($response, [
                'pagination' => [
                    'total' => $invites->total(),
                    'per_page' => $invites->perPage(),
                    'current_page' => $invites->currentPage(),
                    'last_page' => $invites->lastPage(),
                ],
                'data' => $formattedData,
            ], 'Invitation list retrieved successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/member/send/invite
     */
    public function createInvitation(Request $request, Response $response): Response
    {
        try {
            $body = json_decode((string)$request->getBody(), true);
            $inviter = $body['inviter'] ?? null;
            $recipientEmail = $body['recipient_email'] ?? null;
            $roleId = $body['role_id'] ?? null;

            if (!$recipientEmail || !$roleId || !$inviter) {
                return ResponseHandle::error($response, 'Recipient Email, Role ID, and Inviter ID are required', 400);
            }

            $user = User::whereRaw('LOWER(email) = ?', [strtolower($recipientEmail)])->first();
            if ($user) {
                return ResponseHandle::error($response, 'This email is already in use by another member.', 400);
            }

            $invites = InviteMember::where('recipient_email', $recipientEmail)
                ->whereIn('status_id', [4, 5])
                ->get();

            DB::beginTransaction();

            $now = Carbon::now('Asia/Bangkok');

            foreach ($invites as $invite) {
                if ($invite->expires_at > $now) {
                    $invite->expires_at = $now;
                }

                $invite->status_id = 7;
                $invite->save();
            }

            $refCode = uniqid('INV');
            $expiresAt = Carbon::now('Asia/Bangkok')->addDays(7);

            $invite = InviteMember::create([
                'inviter_id' => $inviter,
                'recipient_email' => $recipientEmail,
                'domain' => $_ENV['FRONT_URL'],
                'path' => $_ENV['FRONT_INVITE_PATH'],
                'role_id' => intval($roleId),
                'status_id' => 4,
                'ref_code' => $refCode,
                'expires_at' => $expiresAt
            ]);

            // Load HTML Template
            $templatePath = __DIR__ . '/../templates/invite_member_email.html';
            if (!file_exists($templatePath)) {
                throw new Exception('Email template not found');
            }

            $templateContent = file_get_contents($templatePath);
            $roleName = Role::where('id', $roleId)->value('name');

            $companyName = $_ENV['EMAIL_COMPANY_NAME'] ?? 'Company Name';
            $frontendPath = $_ENV['FRONT_URL'] . "/" . $_ENV['FRONT_INVITE_PATH'];
            $companyLogo = $_ENV['EMAIL_COMPANY_LOGO'] ?? '';
            $emailBaseColor = $_ENV['EMAIL_BASE_COLOR'] ?? '#0B99FF';
            $emailBody = str_replace(
                [
                    '{{frontend_path}}',
                    '{{role_name}}',
                    '{{ref_code}}',
                    '{{recipient_email}}',
                    '{{expires_at}}',
                    '{{company_name}}',
                    '{{company_logo}}',
                    '{{base_color}}',
                ],
                [
                    $frontendPath,
                    $roleName,
                    $refCode,
                    $recipientEmail,
                    $expiresAt,
                    $companyName,
                    $companyLogo,
                    $emailBaseColor,
                ],
                $templateContent
            );

            // Send Email
            $mailer = new PHPMailer(true);

            // Server settings
            $mailer->isSMTP();
            $mailer->Host = $_ENV['SMTP_HOST'];
            $mailer->SMTPAuth = true;
            $mailer->Username = $_ENV['SMTP_USERNAME'];
            $mailer->Password = $_ENV['SMTP_PASSWORD'];
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mailer->Port = (int)$_ENV['SMTP_PORT'];

            // Email Headers
            $mailer->setFrom($_ENV['SMTP_USERNAME'], 'Medmetro Support');
            $mailer->addAddress($recipientEmail);

            // Email Content
            $mailer->isHTML(true);
            $mailer->Subject = 'Invitation to Join Our Platform - Ref ' . $refCode;
            $mailer->Body = $emailBody;
            $mailer->AltBody = "You have been invited to join our platform. Your invitation code is: $refCode";

            $isSendEnabled = filter_var($_ENV['SEND_STATUS'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($isSendEnabled) {
                // Send Email
                $mailer->send();
            }

            DB::commit();

            return ResponseHandle::success($response, $invite, 'The invitation has been successfully created.');
        } catch (PHPMailerException $e) {
            DB::rollBack();
            return ResponseHandle::error($response, 'Mailer Error: ' . $e->getMessage(), 500);
        } catch (Exception $e) {
            DB::rollBack();
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * PUT /v1/member/invite/reject/{id}
     */
    public function rejectInvitation(Request $request, Response $response, $args): Response
    {
        try {
            $inviteId = $args['id'] ?? null;

            if (!$inviteId) {
                return ResponseHandle::error($response, 'Invite ID is required', 400);
            }

            $invite = InviteMember::find($inviteId);

            if (!$invite) {
                return ResponseHandle::error($response, 'Invalid invitation', 404);
            }

            $invite->update([
                'status_id' => 7,
                'expires_at' => Carbon::now('Asia/Bangkok'),
            ]);

            return ResponseHandle::success($response, [], 'The invitation has been successfully rejected.');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/member/invite/verify
     */
    public function verifyInvitation(Request $request, Response $response): Response
    {
        try {
            $body = json_decode((string)$request->getBody(), true);
            $refCode = $body['ref_code'] ?? null;

            if (!$refCode) {
                return ResponseHandle::error($response, 'Reference code is required', 400);
            }

            $invite = InviteMember::where('ref_code', $refCode)
                ->whereIn('status_id', [4, 5])
                ->where('expires_at', '>', Carbon::now('Asia/Bangkok'))
                ->first();

            if (!$invite) {
                return ResponseHandle::error($response, 'Invalid invitation', 400);
            }

            $invite->update(['status_id' => 5]);

            $inviteData = [
                'recipient_email' => $invite->recipient_email,
                'ref_code' => $invite->ref_code,
                'role_id' => $invite->role_id,
                'expires_at' => $invite->expires_at,
            ];

            return ResponseHandle::success($response, $inviteData, 'The invitation has been successfully verified.');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/member/invite/accept
     */
    public function acceptInvitation(Request $request, Response $response): Response
    {
        try {
            $body = json_decode((string)$request->getBody(), true);
            $refCode = $body['ref_code'] ?? null;
            $recipientEmail = $body['recipient_email'] ?? null;
            $password = $body['password'] ?? null;
            $roleId = $body['role_id'] ?? null;
            $phone = $body['phone'] ?? null;
            $firstNameTh = $body['first_name_th'] ?? null;
            $lastNameTh = $body['last_name_th'] ?? null;
            $nicknameTh = $body['nickname_th'] ?? null;
            $firstNameEn = $body['first_name_en'] ?? null;
            $lastNameEn = $body['last_name_en'] ?? null;
            $nicknameEn = $body['nickname_en'] ?? null;

            if (!$refCode || !$recipientEmail || !$password || !$roleId) {
                return ResponseHandle::error($response, 'Ref Code, Email, password and role ID are required', 400);
            }

            $invite = InviteMember::where('ref_code', $refCode)
                ->where('recipient_email', $recipientEmail)
                ->where('status_id', 5)
                ->where('expires_at', '>', Carbon::now('Asia/Bangkok'))
                ->first();

            if (!$invite) {
                return ResponseHandle::error($response, 'Invalid or expired invitation', 400);
            }

            $user = User::whereRaw('LOWER(email) = ?', [strtolower($recipientEmail)])->first();
            if ($user) {
                return ResponseHandle::error($response, 'This email is already in use by another member.', 400);
            }

            Capsule::beginTransaction();

            $user = User::create([
                'email' => $recipientEmail,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'status_id' => 1
            ]);

            UserInfo::create([
                'user_id' => $user->user_id,
                'phone' => $phone
            ]);

            $translations = [
                [
                    'user_id' => $user->user_id,
                    'language_code' => 'th',
                    'first_name' => $firstNameTh ?? '-',
                    'last_name' => $lastNameTh ?? '-',
                    'nickname' => $nicknameTh ?? '-',
                ],
                [
                    'user_id' => $user->user_id,
                    'language_code' => 'en',
                    'first_name' => $firstNameEn ?? '-',
                    'last_name' => $lastNameEn ?? '-',
                    'nickname' => $nicknameEn ?? '-',
                ],
            ];

            foreach ($translations as $translation) {
                UserInfoTranslation::create($translation);
            }


            UserRole::create([
                'user_id' => $user->user_id,
                'role_id' => $roleId
            ]);

            $permissions = Permission::pluck('id')->toArray();
            $excludePermissions = [];

            if ($roleId == 2) {
                $excludePermissions = [2];
            } elseif ($roleId == 3) {
                $excludePermissions = [2, 3, 4, 6, 8, 9, 10];
            }

            $filteredPermissions = array_diff($permissions, $excludePermissions);

            $userPermissions = [];
            foreach ($filteredPermissions as $permissionId) {
                $userPermissions[] = [
                    'user_id' => $user->user_id,
                    'permission_id' => $permissionId,
                ];
            }

            UserPermission::insert($userPermissions);

            $invite->update(['status_id' => 6]);

            Capsule::commit();

            return ResponseHandle::success($response, [], 'The invitation has been successfully accepted.');
        } catch (Exception $e) {
            Capsule::rollBack();
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * GET /v1/member
     */
    public function getMembers(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $userId = $queryParams['user_id'] ?? null;
            $statusIds = $queryParams['status_id'] ?? null;
            $email = $queryParams['email'] ?? null;
            $firstName = $queryParams['first_name'] ?? null;
            $lastName = $queryParams['last_name'] ?? null;
            $nickname = $queryParams['nickname'] ?? null;
            $phone = $queryParams['phone'] ?? null;
            $roleId = $queryParams['role_id'] ?? null;
            $startDate = $queryParams['start_date'] ?? null;
            $endDate = $queryParams['end_date'] ?? null;
            $page = $queryParams['page'] ?? 1;
            $perPage = $queryParams['per_page'] ?? 10;

            $query = User::with([
                'status',
                'userInfo',
                'userInfoTranslation',
                'roles',
                'loginTransaction' => function ($query) {
                    $query->orderBy('created_at', 'desc')
                        ->limit(1);
                }
            ]);

            if ($userId) {
                $query->where('user_id', $userId);
            }

            if ($statusIds) {
                $statusIds = is_array($statusIds) ? $statusIds : explode(',', $statusIds);
                $query->whereIn('status_id', $statusIds);
            }

            if ($email) {
                $query->where('email', 'LIKE', '%' . $email . '%');
            }

            if ($firstName) {
                $query->whereHas('userInfoTranslation', function ($q) use ($firstName) {
                    $q->where('first_name', 'LIKE', '%' . $firstName . '%');
                });
            }

            if ($lastName) {
                $query->whereHas('userInfoTranslation', function ($q) use ($lastName) {
                    $q->where('last_name', 'LIKE', '%' . $lastName . '%');
                });
            }

            if ($nickname) {
                $query->whereHas('userInfoTranslation', function ($q) use ($nickname) {
                    $q->where('nickname', 'LIKE', '%' . $nickname . '%');
                });
            }

            if ($phone) {
                $query->whereHas('userInfo', function ($q) use ($phone) {
                    $q->where('phone', 'LIKE', '%' . $phone . '%');
                });
            }

            if ($roleId) {
                $roleIds = is_array($roleId) ? $roleId : explode(',', $roleId);
                $query->whereHas('roles', function ($q) use ($roleIds) {
                    $q->whereIn('role_id', $roleIds);
                });
            }

            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            } elseif ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            } elseif ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            $members = $query->orderBy('user_id', 'asc')->paginate($perPage, ['*'], 'page', $page);

            $memberData = collect($members->items())->map(function ($userModel) {
                return [
                    'user_id' => $userModel->user_id,
                    'email' => $userModel->email,
                    'created_at' => $userModel->created_at,
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
                    'last_login' => $userModel->loginTransaction->isNotEmpty() ? [
                        'status' => $userModel->loginTransaction->first()->status,
                        'created_at' => $userModel->loginTransaction->first()->created_at,
                    ] : null
                ];
            });

            return ResponseHandle::success($response, [
                'pagination' => [
                    'total' => $members->total(),
                    'per_page' => $members->perPage(),
                    'current_page' => $members->currentPage(),
                    'last_page' => $members->lastPage(),
                ],
                'data' => $memberData
            ], 'Member list retrieved successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * GET /v1/member/batch
     */
    public function getMemberBatch(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $userIds = $queryParams['ids'] ?? null;

            if (!$userIds) {
                return ResponseHandle::error($response, 'User IDs are required', 400);
            }

            $userIdsArray = is_array($userIds) ? $userIds : explode(',', $userIds);

            $users = User::with([
                'status',
                'userInfo',
                'userInfoTranslation',
                'roles'
            ])->whereIn('user_id', $userIdsArray)->get();

            if ($users->isEmpty()) {
                return ResponseHandle::error($response, 'No users found for the provided IDs', 404);
            }

            $membersData = $users->map(function ($user) {
                return [
                    'user_id' => $user->user_id,
                    'email' => $user->email,
                    'avatar_base_url' => $user->avatar_base_url,
                    'avatar_lazy_url' => $user->avatar_lazy_url,
                    'user_info' => $user->userInfoTranslation->map(function ($translation) {
                        return [
                            'language_code' => $translation->language_code,
                            'first_name' => $translation->first_name,
                            'last_name' => $translation->last_name,
                            'nickname' => $translation->nickname,
                        ];
                    })->toArray(),
                ];
            });

            // ส่งผลลัพธ์กลับในรูปแบบ array
            return ResponseHandle::success($response, $membersData, 'Members retrieved successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/member
     */
    public function createMember(Request $request, Response $response): Response
    {
        try {
            $body = json_decode((string)$request->getBody(), true);
            $email = $body['email'] ?? null;
            $password = $body['password'] ?? null;
            $roleId = $body['role_id'] ?? null;
            $phone = $body['phone'] ?? null;
            $firstNameTh = $body['first_name_th'] ?? null;
            $lastNameTh = $body['last_name_th'] ?? null;
            $nicknameTh = $body['nickname_th'] ?? null;
            $firstNameEn = $body['first_name_en'] ?? null;
            $lastNameEn = $body['last_name_en'] ?? null;
            $nicknameEn = $body['nickname_en'] ?? null;

            if (!$email || !$password || !$roleId) {
                return ResponseHandle::error($response, 'Email, password and role ID are required', 400);
            }

            if (User::where('email', $email)->exists()) {
                return ResponseHandle::error($response, 'This email is already in use.', 400);
            }

            Capsule::beginTransaction();

            $user = User::create([
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'status_id' => 1
            ]);

            UserInfo::create([
                'user_id' => $user->user_id,
                'phone' => $phone
            ]);

            $translations = [
                [
                    'user_id' => $user->user_id,
                    'language_code' => 'th',
                    'first_name' => $firstNameTh ?? '-',
                    'last_name' => $lastNameTh ?? '-',
                    'nickname' => $nicknameTh ?? '-',
                ],
                [
                    'user_id' => $user->user_id,
                    'language_code' => 'en',
                    'first_name' => $firstNameEn ?? '-',
                    'last_name' => $lastNameEn ?? '-',
                    'nickname' => $nicknameEn ?? '-',
                ],
            ];

            foreach ($translations as $translation) {
                UserInfoTranslation::create($translation);
            }


            UserRole::create([
                'user_id' => $user->user_id,
                'role_id' => $roleId
            ]);

            $permissions = Permission::pluck('id')->toArray();
            $excludePermissions = [];

            if ($roleId == 2) {
                $excludePermissions = [2];
            } elseif ($roleId == 3) {
                $excludePermissions = [2, 3, 4, 6, 8, 9, 10];
            }

            $filteredPermissions = array_diff($permissions, $excludePermissions);

            $userPermissions = [];
            foreach ($filteredPermissions as $permissionId) {
                $userPermissions[] = [
                    'user_id' => $user->user_id,
                    'permission_id' => $permissionId,
                ];
            }

            UserPermission::insert($userPermissions);

            Capsule::commit();

            return ResponseHandle::success($response, [], 'The member has been successfully created.');
        } catch (Exception $e) {
            Capsule::rollBack();
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /v1/member/{id}
     */
    public function permanentlyDeleteMember(Request $request, Response $response, $args): Response
    {
        try {
            $userId = $args['id'] ?? null;

            if (!$userId) {
                return ResponseHandle::error($response, 'User ID is required', 400);
            }

            $user = User::find($userId);

            if (!$user) {
                return ResponseHandle::error($response, 'User not found', 404);
            }

            $user->delete();

            return ResponseHandle::success($response, [], 'The member has been permanently deleted.');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /v1/member{id}/soft
     */
    public function softDeleteMember(Request $request, Response $response, $args): Response
    {
        try {
            $userId = $args['id'] ?? null;

            if (!$userId) {
                return ResponseHandle::error($response, 'User ID is required', 400);
            }

            $user = User::find($userId);

            if (!$user) {
                return ResponseHandle::error($response, 'User not found', 404);
            }

            $user->status_id = 3;
            $user->save();

            return ResponseHandle::success($response, [], 'The member has been successfully soft deleted.');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * PUT /v1/member/suspend/{id}
     */
    public function suspendMember(Request $request, Response $response, $args): Response
    {
        try {
            $userId = $args['id'] ?? null;

            if (!$userId) {
                return ResponseHandle::error($response, 'User ID is required', 400);
            }

            $user = User::find($userId);

            if (!$user) {
                return ResponseHandle::error($response, 'User not found', 404);
            }

            $user->status_id = 2;
            $user->save();

            return ResponseHandle::success($response, [], 'The member has been successfully suspended.');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * PUT /v1/member/active/{id}
     */
    public function activeMember(Request $request, Response $response, $args): Response
    {
        try {
            $userId = $args['id'] ?? null;

            if (!$userId) {
                return ResponseHandle::error($response, 'User ID is required', 400);
            }

            $user = User::find($userId);

            if (!$user) {
                return ResponseHandle::error($response, 'User not found', 404);
            }

            $user->status_id = 1;
            $user->save();

            return ResponseHandle::success($response, [], 'The member has been successfully activated.');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * PUT /v1/member/change-role/{user_id}
     */
    public function changeRoleMember(Request $request, Response $response, $args): Response
    {
        try {
            $userId = $args['user_id'] ?? null;

            if (!$userId) {
                return ResponseHandle::error($response, 'User ID is required', 400);
            }

            $user = User::find($userId);

            if (!$user) {
                return ResponseHandle::error($response, 'User not found', 404);
            }

            $body = json_decode((string)$request->getBody(), true);
            if (isset($body['new_role_id'])) {
                $userRole = UserRole::where('user_id', $userId)->first();
                if (!$userRole) {
                    return ResponseHandle::error($response, 'UserRole not found for this user', 404);
                }
                $userRole->role_id = $body['new_role_id'];
                $userRole->save();
            }

            return ResponseHandle::success($response, [], 'The member\'s role has been successfully updated.');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }
}
