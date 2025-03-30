<?php

namespace App\Controllers;

use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Helpers\ResponseHandle;
use App\Models\Otps;
use App\Models\User;
use Illuminate\Support\Carbon;
use PHPMailer\PHPMailer\PHPMailer;
use Illuminate\Database\Capsule\Manager as DB;

class OtpController
{
    // GET /v1/otp
    public function getAll(Request $request, Response $response): Response
    {
        try {
            $query = Otps::query();

            // Filter by id
            if ($request->getQueryParams()['id'] ?? false) {
                $query->where('id', $request->getQueryParams()['id']);
            }

            // Filter by type
            if ($request->getQueryParams()['type'] ?? false) {
                $query->where('type', $request->getQueryParams()['type']);
            }

            // Filter by is_used
            if (isset($request->getQueryParams()['is_used'])) {
                $query->where('is_used', filter_var($request->getQueryParams()['is_used'], FILTER_VALIDATE_BOOLEAN));
            }

            // Filter OTPs that are still valid (not expired)
            // $query->where('expires_at', '>', Carbon::now('Asia/Bangkok'));

            // Scope by created_at
            if ($request->getQueryParams()['start_date'] ?? false) {
                $query->where('created_at', '>=', $request->getQueryParams()['start_date']);
            }
            if ($request->getQueryParams()['end_date'] ?? false) {
                $query->where('created_at', '<=', $request->getQueryParams()['end_date']);
            }

            $order = strtolower($queryParams['order'] ?? 'desc');
            if (!in_array($order, ['asc', 'desc'])) {
                $order = 'desc';
            }
            $query->orderBy('id', $order);

            // Pagination
            $perPage = $request->getQueryParams()['per_page'] ?? 10;
            $page = $request->getQueryParams()['page'] ?? 1;
            $otps = $query->paginate($perPage, ['*'], 'page', $page);

            return ResponseHandle::success($response, [
                'pagination' => [
                    'total' => $otps->total(),
                    'per_page' => $otps->perPage(),
                    'current_page' => $otps->currentPage(),
                    'last_page' => $otps->lastPage(),
                ],
                'data' => $otps->items()
            ], 'Data retrieved successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    // POST /v1/otp
    public function sendOTP(Request $request, Response $response): Response
    {
        try {
            $body = json_decode((string)$request->getBody(), true);
            $type = $body['type'] ?? null;
            $recipientEmail = $body['recipient_email'] ?? null;
            $ref = $body['ref'] ?? null;

            if (!$type || !$recipientEmail || !$ref) {
                return ResponseHandle::error($response, 'All required fields must be provided', 400);
            }

            $user = User::whereRaw('LOWER(email) = ?', [strtolower($recipientEmail)])->first();
            if (!$user) {
                return ResponseHandle::success($response, [], 'If your email exists, you will receive an OTP shortly.');
            }

            DB::beginTransaction();

            $forgots = DB::table('otps')
                ->where('recipient_email', $recipientEmail)
                ->where('is_used', false)
                ->where('type', 'forgot-password')
                ->where('expires_at', '>', Carbon::now('Asia/Bangkok'))
                ->lockForUpdate()
                ->get();

            foreach ($forgots as $forgot) {
                DB::table('otps')
                    ->where('id', $forgot->id)
                    ->update(['expires_at' => Carbon::now('Asia/Bangkok')->subSeconds(1)]);
            }

            $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = Carbon::now('Asia/Bangkok')->addMinutes(5);

            Otps::create([
                'recipient_email' => $recipientEmail,
                "type" => $type,
                'otp_code' => $otpCode,
                'ref' => $ref,
                'expires_at' => $expiresAt
            ]);

            $templatePath = __DIR__ . '/../templates/otp_email.html';
            if (!file_exists($templatePath)) {
                throw new Exception('Email template not found');
            }

            $templateContent = file_get_contents($templatePath);
            if ($templateContent === false) {
                throw new Exception('Failed to read email template');
            }

            $companyName = $_ENV['EMAIL_COMPANY_NAME'] ?? 'Company Name';
            $emailBaseColor = $_ENV['EMAIL_BASE_COLOR'] ?? '#0B99FF';

            $emailBody = str_replace(
                ['{{ref}}', '{{otp_code}}', '{{recipient_email}}', '{{company_name}}', '{{base_color}}'],
                [$ref, $otpCode, $recipientEmail, $companyName, $emailBaseColor],
                $templateContent
            );

            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $_ENV['SMTP_HOST'];
            $mailer->SMTPAuth = true;
            $mailer->Username = $_ENV['SMTP_USERNAME'];
            $mailer->Password = $_ENV['SMTP_PASSWORD'];
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mailer->Port = (int)$_ENV['SMTP_PORT'];

            $mailer->setFrom($_ENV['SMTP_USERNAME'], $_ENV['EMAIL_FROM_NAME'] ?? 'Support');
            $mailer->addAddress($recipientEmail);
            $mailer->isHTML(true);
            $mailer->Subject = 'Your OTP Code';
            $mailer->Body = $emailBody;
            $mailer->AltBody = "Your OTP code is: $otpCode";

            $isSendEnabled = filter_var($_ENV['SEND_STATUS'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
            if ($isSendEnabled) {
                $mailer->send();
            }

            DB::commit();

            return ResponseHandle::success($response, [], 'If your email exists, you will receive an OTP shortly.');
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            DB::rollBack();
            return ResponseHandle::error($response, 'Mailer Error: ' . $e->getMessage(), 500);
        } catch (Exception $e) {
            DB::rollBack();
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    // POST /v1/otp/verify
    public function verify(Request $request, Response $response): Response
    {
        try {
            $body = json_decode((string)$request->getBody(), true);
            $type = $body['type'] ?? null;
            $recipientEmail = $body['recipient_email'] ?? null;
            $ref = $body['ref'] ?? null;
            $otpCode = $body['otp_code'] ?? null;

            if (!$type || !$recipientEmail || !$ref || !$otpCode) {
                return ResponseHandle::error($response, 'All required fields must be provided', 400);
            }

            DB::beginTransaction();

            $otp = Otps::where('recipient_email', $recipientEmail)
                ->where('ref', $ref)
                ->where('otp_code', $otpCode)
                ->where('type', $type)
                ->where('is_used', false)
                ->where('expires_at', '>', Carbon::now('Asia/Bangkok'))
                ->lockForUpdate()
                ->first();

            if (!$otp) {
                DB::rollBack();
                return ResponseHandle::error($response, 'Invalid or expired OTP', 400);
            }

            $otp->is_used = true;
            $otp->save();

            DB::commit();

            return ResponseHandle::success($response, [], 'OTP verified successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }
}
