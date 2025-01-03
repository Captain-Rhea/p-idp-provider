<?php

namespace App\Controllers;

use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
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
            $email = $queryParams['email'] ?? null;
            $statusIds = $queryParams['status_id'] ?? null;
            $startDate = $queryParams['start_date'] ?? null;
            $endDate = $queryParams['end_date'] ?? null;
            $page = $queryParams['page'] ?? 1;
            $perPage = $queryParams['per_page'] ?? 10;

            $query = InviteMember::with(['inviter', 'status']);

            // Apply filters
            if ($email) {
                $query->where('email', 'LIKE', '%' . $email . '%');
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
                    'email' => $invite->email,
                    'ref_code' => $invite->ref_code,
                    'status' => [
                        'id' => $invite->status->id,
                        'name' => $invite->status->name,
                        'description' => $invite->status->description,
                    ],
                    'inviter' => [
                        'user_id' => $invite->inviter->user_id,
                        'email' => $invite->inviter->email,
                        'avatar_url' => $invite->inviter->avatar_url,
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
            $recipientEmail = $body['email'] ?? null;
            $roleId = $body['role_id'] ?? null;

            $inviter = $request->getAttribute('user');

            if (!$recipientEmail || !$roleId || !$inviter) {
                return ResponseHandle::error($response, 'Recipient Email, Role ID, and Inviter ID are required', 400);
            }

            $user = User::whereRaw('LOWER(email) = ?', [strtolower($recipientEmail)])->first();
            if ($user) {
                return ResponseHandle::error($response, 'This email is already in use by another member.', 400);
            }

            $invites = InviteMember::where('email', $recipientEmail)
                ->whereIn('status_id', [5, 6])
                ->where('expires_at', '>', Carbon::now('Asia/Bangkok'))
                ->get();

            foreach ($invites as $invite) {
                $invite->expires_at = Carbon::now('Asia/Bangkok');
                $invite->status_id = 8;
                $invite->save();
            }

            $refCode = uniqid('INV');
            $expiresAt = Carbon::now('Asia/Bangkok')->addDays(7);

            $invite = InviteMember::create([
                'inviter_id' => $inviter['user_id'],
                'email' => $recipientEmail,
                'role_id' => $roleId,
                'status_id' => 5,
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

            $frontendPath = $_ENV['FRONT_URL'] . "/" . $_ENV['FRONT_INVITE_PATH'];
            $emailBody = str_replace(
                ['{{frontend_path}}', '{{role_name}}', '{{ref_code}}', '{{recipient_email}}', '{{expires_at}}'],
                [$frontendPath, $roleName, $refCode, $recipientEmail, $expiresAt],
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

            // Send Email
            // $mailer->send();

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
            $inviteId = $args['id'] ?? null;

            if (!$inviteId) {
                return ResponseHandle::error($response, 'Invite ID is required', 400);
            }

            $invite = InviteMember::find($inviteId);

            if (!$invite) {
                return ResponseHandle::error($response, 'Invalid invitation', 404);
            }

            $invite->update([
                'status_id' => 8,
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
            $body = json_decode((string)$request->getBody(), true);
            $refCode = $body['ref_code'] ?? null;

            if (!$refCode) {
                return ResponseHandle::error($response, 'Reference code is required', 400);
            }

            $invite = InviteMember::where('ref_code', $refCode)
                ->whereIn('status_id', [5, 6])
                ->first();

            if (!$invite) {
                return ResponseHandle::error($response, 'Invalid invitation', 400);
            }

            $invite->update(['status_id' => 6]);

            return ResponseHandle::success($response, [], 'Invitation verify successfully');
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
            $email = $body['email'] ?? null;

            if (!$refCode || !$email) {
                return ResponseHandle::error($response, 'Reference code and email are required', 400);
            }

            $invite = InviteMember::where('ref_code', $refCode)
                ->where('email', $email)
                ->where('status_id', 6)
                ->where('expires_at', '>', Carbon::now('Asia/Bangkok'))
                ->first();

            if (!$invite) {
                return ResponseHandle::error($response, 'Invalid or expired invitation', 400);
            }

            $invite->update(['status_id' => 7]);

            return ResponseHandle::success($response, [], 'Invitation accepted successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/auth/create/member
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
}
