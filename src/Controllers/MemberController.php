<?php

namespace App\Controllers;

use Exception;
use Illuminate\Support\Carbon;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Models\InviteMember;
use App\Helpers\ResponseHandle;

class MemberController
{
    /**
     * POST /v1/invite-member
     */
    public function createInvitation(Request $request, Response $response): Response
    {
        try {
            $body = json_decode((string)$request->getBody(), true);
            $inviterId = $body['inviter_id'] ?? null;
            $email = $body['email'] ?? null;

            if (!$inviterId || !$email) {
                return ResponseHandle::error($response, 'Inviter ID and email are required', 400);
            }

            if (InviteMember::where('email', $email)->where('status', 'pending')->exists()) {
                return ResponseHandle::error($response, 'An active invitation already exists for this email', 400);
            }

            $refCode = uniqid('INV');
            $expiresAt = Carbon::now()->addDays(7);

            $invite = InviteMember::create([
                'inviter_id' => $inviterId,
                'email' => $email,
                'status' => 'pending',
                'ref_code' => $refCode,
                'expires_at' => $expiresAt,
            ]);

            return ResponseHandle::success($response, [
                'ref_code' => $invite->ref_code,
                'expires_at' => $invite->expires_at->toDateTimeString(),
            ], 'Invitation created successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * GET /v1/invite-member/{ref_code}
     */
    public function getInvitation(Request $request, Response $response, array $args): Response
    {
        try {
            $refCode = $args['ref_code'] ?? null;

            if (!$refCode) {
                return ResponseHandle::error($response, 'Reference code is required', 400);
            }

            $invite = InviteMember::where('ref_code', $refCode)->first();

            if (!$invite) {
                return ResponseHandle::error($response, 'Invitation not found', 404);
            }

            return ResponseHandle::success($response, $invite->toArray(), 'Invitation retrieved successfully');
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
