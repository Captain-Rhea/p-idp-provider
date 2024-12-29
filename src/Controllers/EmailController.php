<?php

namespace App\Controllers;

use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Helpers\ResponseHandle;
use App\Models\OtpTransaction;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Illuminate\Support\Carbon;

class EmailController
{
    /**
     * POST /v1/email/send/verify
     */
    public function sendVerifyEmail(Request $request, Response $response): Response
    {
        return $this->sendOtp($request, $response, 'verify_email');
    }

    /**
     * POST /v1/email/send/reset
     */
    public function sendResetPasswordEmail(Request $request, Response $response): Response
    {
        return $this->sendOtp($request, $response, 'password_reset');
    }

    /**
     * Handle sending OTP for specific purposes.
     */
    private function sendOtp(Request $request, Response $response, string $purpose): Response
    {
        try {
            $body = json_decode((string)$request->getBody(), true);
            $recipientEmail = $body['email'] ?? null;

            if (!$recipientEmail) {
                return ResponseHandle::error($response, 'Recipient email is required', 400);
            }

            // Mark previous OTPs as expired
            OtpTransaction::where('email', $recipientEmail)
                ->where('purpose', $purpose)
                ->where('is_used', false)
                ->where('expires_at', '>', Carbon::now())
                ->update(['expires_at' => Carbon::now()]);

            // Generate new OTP
            $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $refCode = uniqid('REF');

            // Save new OTP to database
            OtpTransaction::create([
                'email' => $recipientEmail,
                'ref_code' => $refCode,
                'otp_code' => $otpCode,
                'purpose' => $purpose,
                'expires_at' => Carbon::now()->addMinutes(10),
            ]);

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
            $mailer->setFrom($_ENV['SMTP_USERNAME'], 'MedMetro Support');
            $mailer->addAddress($recipientEmail);

            // Email Content
            $mailer->isHTML(true);
            $mailer->Subject = $purpose === 'verify_email' ? 'Email Verification' : 'Reset Password Confirmation';
            $mailer->Body = $this->generateOtpEmail($otpCode, $purpose);
            $mailer->AltBody = "Your OTP code is: $otpCode";

            // Send Email
            $mailer->send();

            return ResponseHandle::success($response, ['ref_code' => $refCode], 'OTP sent successfully');
        } catch (PHPMailerException $e) {
            return ResponseHandle::error($response, 'Mailer Error: ' . $e->getMessage(), 500);
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * Generate OTP Email HTML Content
     */
    private function generateOtpEmail(string $otpCode, string $purpose): string
    {
        $purposeText = $purpose === 'verify_email' ? 'Email Verification' : 'Reset Password Confirmation';

        return <<<HTML
        <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .otp-code { font-size: 24px; font-weight: bold; color: #333; }
                </style>
            </head>
            <body>
                <div class="container">
                    <h2>$purposeText</h2>
                    <p>Hello,</p>
                    <p>Your OTP code is:</p>
                    <p class="otp-code">$otpCode</p>
                    <p>This OTP will expire in 10 minutes.</p>
                    <p>If you did not request this, please ignore this email.</p>
                    <p>Best regards,<br>MedMetro Support</p>
                </div>
            </body>
        </html>
        HTML;
    }

    /**
     * POST /v1/otp/verify
     */
    public function verifyOtp(Request $request, Response $response): Response
    {
        try {
            $body = json_decode((string)$request->getBody(), true);
            $otpCode = $body['otp_code'] ?? null;
            $refCode = $body['ref_code'] ?? null;

            if (!$otpCode || !$refCode) {
                return ResponseHandle::error($response, 'OTP code and reference code are required', 400);
            }

            // Find OTP record in the database
            $otpTransaction = OtpTransaction::where('ref_code', $refCode)
                ->where('otp_code', $otpCode)
                ->first();

            if (!$otpTransaction) {
                return ResponseHandle::error($response, 'Invalid OTP or reference code', 400);
            }

            // Check if OTP is expired
            if ($otpTransaction->expires_at < Carbon::now()) {
                return ResponseHandle::error($response, 'OTP has expired', 400);
            }

            // Check if OTP is already used
            if ($otpTransaction->is_used) {
                return ResponseHandle::error($response, 'OTP has already been used', 400);
            }

            // Mark OTP as used
            $otpTransaction->is_used = true;
            $otpTransaction->save();

            return ResponseHandle::success($response, [], 'OTP verified successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }
}
