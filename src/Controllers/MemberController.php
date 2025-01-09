<?php

namespace App\Controllers;

use App\Helpers\JWTHelper;
use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Illuminate\Support\Carbon;
use App\Models\InviteMember;
use App\Helpers\ResponseHandle;
use App\Helpers\VerifyUserStatus;
use App\Models\Role;
use App\Models\User;
use App\Models\Permission;
use App\Models\UserInfo;
use App\Models\UserInfoTranslation;
use App\Models\UserPermission;
use App\Models\UserRole;
use Illuminate\Database\Capsule\Manager as Capsule;

class MemberController
{
    /**
     * GET /v1/member/invite
     */
    public function getInvitation(Request $request, Response $response): Response
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

            // Paginate results
            $invites = $query->paginate($perPage, ['*'], 'page', $page);

            $formattedData = collect($invites->items())->map(function ($invite) {
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
            $recipientEmail = $body['recipient_email'] ?? null;
            $roleId = $body['role_id'] ?? null;
            $inviter = $user;

            if (!$recipientEmail || !$roleId || !$inviter) {
                return ResponseHandle::error($response, 'Recipient Email, Role ID, and Inviter ID are required', 400);
            }

            $user = User::whereRaw('LOWER(email) = ?', [strtolower($recipientEmail)])->first();
            if ($user) {
                return ResponseHandle::error($response, 'This email is already in use by another member.', 400);
            }

            $invites = InviteMember::where('recipient_email', $recipientEmail)
                ->whereIn('status_id', [4, 5])
                ->where('expires_at', '>', Carbon::now('Asia/Bangkok'))
                ->get();

            foreach ($invites as $invite) {
                $invite->expires_at = Carbon::now('Asia/Bangkok');
                $invite->status_id = 7;
                $invite->save();
            }

            $refCode = uniqid('INV');
            $expiresAt = Carbon::now('Asia/Bangkok')->addDays(7);

            $invite = InviteMember::create([
                'inviter_id' => $inviter['user_id'],
                'recipient_email' => $recipientEmail,
                'domain' => $_ENV['FRONT_URL'],
                'path' => $_ENV['FRONT_INVITE_PATH'],
                'role_id' => $roleId,
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

            return ResponseHandle::success($response, $invite, 'Invitation created successfully');
        } catch (PHPMailerException $e) {
            return ResponseHandle::error($response, 'Mailer Error: ' . $e->getMessage(), 500);
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * PUT /v1/member/invite/reject/{id}
     */
    public function rejectInvitation(Request $request, Response $response, $args): Response
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

            return ResponseHandle::success($response, [], 'Invitation rejected successfully');
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

            return ResponseHandle::success($response, $inviteData, 'Invitation verify successfully');
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

            if (!$refCode || !$recipientEmail || !$password || !$roleId || !$phone) {
                return ResponseHandle::error($response, 'Ref Code, Email, password, role ID and phone are required', 400);
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

            return ResponseHandle::success($response, [], 'Invitation accepted successfully');
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
            $token = $queryParams['token'] ?? '';
            $user = JWTHelper::getUser($token);
            if (!$user) {
                return ResponseHandle::error($response, 'Unauthorized', 401);
            }

            $statusCheckResponse = VerifyUserStatus::check($user->status_id, $response);
            if ($statusCheckResponse) {
                return $statusCheckResponse;
            }

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
                'roles'
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

            // Paginate Results
            $members = $query->paginate($perPage, ['*'], 'page', $page);

            // จัดรูปแบบข้อมูลสำหรับ Response
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
                    })->toArray()
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
     * POST /v1/member/create
     */
    public function createMember(Request $request, Response $response): Response
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

            $roles = $user->roles()->pluck('role_id')->toArray();
            if ($roles[0] !== 1) {
                return ResponseHandle::error($response, 'Forbidden: You do not have permission to modify this resource', 403);
            }

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

            if (!$email || !$password || !$roleId || !$phone) {
                return ResponseHandle::error($response, 'Email, password, role ID and phone are required', 400);
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

            return ResponseHandle::success($response, [], 'Member has been created successfully');
        } catch (Exception $e) {
            Capsule::rollBack();
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /v1/member/delete/{id}
     */
    public function permanentlyDeleteMember(Request $request, Response $response, $args): Response
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

            $userId = $args['id'] ?? null;

            if (!$userId) {
                return ResponseHandle::error($response, 'User ID is required', 400);
            }

            $user = User::find($userId);

            if (!$user) {
                return ResponseHandle::error($response, 'User not found', 404);
            }

            $user->delete();

            return ResponseHandle::success($response, [], 'Member has been permanently deleted successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /v1/member/delete/soft/{id}
     */
    public function softDeleteMember(Request $request, Response $response, $args): Response
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

            return ResponseHandle::success($response, [], 'Member has been soft deleted successfully');
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

            return ResponseHandle::success($response, [], 'Member has been suspended successfully');
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

            return ResponseHandle::success($response, [], 'Member has been actived successfully');
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

            return ResponseHandle::success($response, [], 'Member has been actived successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }
}
