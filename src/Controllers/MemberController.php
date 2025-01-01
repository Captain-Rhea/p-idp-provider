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
            $statusId = $queryParams['status_id'] ?? null;
            $startDate = $queryParams['start_date'] ?? null;
            $endDate = $queryParams['end_date'] ?? null;
            $page = $queryParams['page'] ?? 1;
            $perPage = $queryParams['per_page'] ?? 10;

            $query = InviteMember::query();

            // Apply filters
            if ($email) {
                $query->where('email', 'LIKE', '%' . $email . '%');
            }

            if ($statusId) {
                $query->where('status_id', $statusId);
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

            return ResponseHandle::success($response, [
                'pagination' => [
                    'total' => $invites->total(),
                    'per_page' => $invites->perPage(),
                    'current_page' => $invites->currentPage(),
                    'last_page' => $invites->lastPage(),
                ],
                'data' => $invites->items(),
            ], 'Invitation list retrieved successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/invite-member
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

            InviteMember::where('email', $recipientEmail)
                ->whereIn('status_id', [5, 6])
                ->where('expires_at', '>', Carbon::now('Asia/Bangkok'))
                ->update([
                    'expires_at' => Carbon::now('Asia/Bangkok'),
                    'status_id' => 8,
                ]);

            $refCode = uniqid('INV');
            $expiresAt = Carbon::now('Asia/Bangkok')->addDays(7);

            $invite = InviteMember::create([
                'inviter_id' => $inviter['user_id'],
                'email' => $recipientEmail,
                'role_id' => $roleId,
                'status_id' => 5,
                'ref_code' => $refCode,
                'expires_at' => $expiresAt,
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
            $mailer->Subject = 'Invitation to Join Our Platform';
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
     * POST /v1/invite-member/accept
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
                ->where('status', 'pending')
                ->where('expires_at', '>', Carbon::now())
                ->first();

            if (!$invite) {
                return ResponseHandle::error($response, 'Invalid or expired invitation', 400);
            }

            // Update invitation status
            $invite->update(['status' => 'accepted']);

            return ResponseHandle::success($response, [], 'Invitation accepted successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/invite-member/reject
     */
    public function rejectInvitation(Request $request, Response $response): Response
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
                ->where('status', 'pending')
                ->first();

            if (!$invite) {
                return ResponseHandle::error($response, 'Invalid invitation', 400);
            }

            // Update invitation status
            $invite->update(['status' => 'rejected']);

            return ResponseHandle::success($response, [], 'Invitation rejected successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }
}
