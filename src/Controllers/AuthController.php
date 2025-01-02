<?php

namespace App\Controllers;

use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Helpers\ResponseHandle;
use App\Helpers\VerifyUserStatus;
use App\Models\User;
use App\Utils\TokenUtils;
use App\Helpers\LoginTransactionHandle;
use App\Models\ForgotPassword;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Illuminate\Support\Carbon;

class AuthController
{
    /**
     * POST /v1/auth/login - Login and generate JWT token
     */
    public function login(Request $request, Response $response): Response
    {
        try {
            $body = json_decode((string)$request->getBody(), true);
            $email = $body['email'] ?? null;
            $password = $body['password'] ?? null;

            if (!$email || !$password) {
                return ResponseHandle::error($response, 'Email and password are required', 400);
            }

            $user = User::where('email', $email)->first();

            if (!$user || !password_verify($password, $user->password)) {

                LoginTransactionHandle::logTransaction(
                    $user->user_id,
                    'failed',
                    $request->getServerParams()['REMOTE_ADDR'],
                    $request->getHeaderLine('User-Agent')
                );

                return ResponseHandle::error($response, 'Invalid email or password', 401);
            }

            $statusCheckResponse = VerifyUserStatus::check($user->status_id, $response);
            if ($statusCheckResponse) {
                return $statusCheckResponse;
            }

            $roles = $user->roles()->pluck('name')->toArray();

            $token = TokenUtils::generateToken([
                'user_id' => $user->user_id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'role' => $roles
            ], 60 * 60 * 48);

            LoginTransactionHandle::logTransaction(
                $user->user_id,
                'success',
                $request->getServerParams()['REMOTE_ADDR'],
                $request->getHeaderLine('User-Agent')
            );

            return ResponseHandle::success($response, ['token' => $token], 'Login successful');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/auth/verify-token
     */
    public function verifyToken(Request $request, Response $response): Response
    {
        try {
            $body = json_decode((string)$request->getBody(), true);
            $token = $body['token'] ?? null;

            if (!$token) {
                return ResponseHandle::error($response, 'Token is required', 400);
            }

            $isValidToken = TokenUtils::isValidToken($token);
            if (!$isValidToken) {
                return ResponseHandle::error($response, 'Invalid or expired token', 401);
            }

            return ResponseHandle::success($response, [], 'Token verified successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/auth/reset-password
     */
    public function resetPassword(Request $request, Response $response): Response
    {
        try {
            $body = json_decode((string)$request->getBody(), true);
            $email = $body['email'] ?? null;
            $newPassword = $body['new_password'] ?? null;

            if (!$email || !$newPassword) {
                return ResponseHandle::error($response, 'Email and new password are required', 400);
            }

            $user = User::whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
            if (!$user) {
                return ResponseHandle::error($response, 'User not found', 404);
            }

            $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
            $user->save();

            return ResponseHandle::success($response, [], 'Password reset successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/auth/send/forgot-mail
     */
    public function sendForgotMail(Request $request, Response $response): Response
    {
        try {
            $body = json_decode((string)$request->getBody(), true);
            $recipientEmail = $body['email'] ?? null;

            if (!$recipientEmail) {
                return ResponseHandle::error($response, 'Recipient email is required', 400);
            }

            $user = User::whereRaw('LOWER(email) = ?', [strtolower($recipientEmail)])->first();
            if (!$user) {
                return ResponseHandle::error($response, 'User not found', 404);
            }

            // Mark previous reset keys as expired
            $forgots = ForgotPassword::where('email', $recipientEmail)
                ->where('is_used', false)
                ->where('expires_at', '>', Carbon::now('Asia/Bangkok'))
                ->get();

            foreach ($forgots as $forgot) {
                $forgot->expires_at = Carbon::now('Asia/Bangkok');
                $forgot->save();
            }

            // Generate Reset Key
            $resetKey = uniqid('RESET-', true);

            // Save reset key to database
            ForgotPassword::create([
                'email' => $recipientEmail,
                'reset_key' => $resetKey,
                'is_used' => false,
                'sent_at' => Carbon::now('Asia/Bangkok'),
                'expires_at' => Carbon::now('Asia/Bangkok')->addHours(2)
            ]);

            // Load HTML Template
            $templatePath = __DIR__ . '/../templates/reset_password_email.html';
            if (!file_exists($templatePath)) {
                throw new Exception('Email template not found');
            }

            $frontendPath = $_ENV['FRONT_URL'] . "/" . $_ENV['FRONT_RESET_PATH'];
            $templateContent = file_get_contents($templatePath);
            $emailBody = str_replace(
                ['{{frontend_path}}', '{{reset_key}}', '{{recipient_email}}'],
                [$frontendPath, $resetKey, $recipientEmail],
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
            $mailer->Subject = 'Password Reset Request';
            $mailer->Body = $emailBody;
            $mailer->AltBody = "Your reset key is: $resetKey";

            // Send Email
            $mailer->send();

            return ResponseHandle::success($response, [], 'Reset password email sent successfully');
        } catch (PHPMailerException $e) {
            // Handle PHPMailer-specific exceptions
            return ResponseHandle::error($response, 'Mailer Error: ' . $e->getMessage(), 500);
        } catch (Exception $e) {
            // Handle general exceptions
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/auth/send/forgot-mail/verify
     */
    public function forgotMailVerify(Request $request, Response $response): Response
    {
        try {
            $body = json_decode((string)$request->getBody(), true);
            $resetKey = $body['reset_key'] ?? null;

            if (!$resetKey) {
                return ResponseHandle::error($response, 'Reset key is required', 400);
            }

            $resetRecord = ForgotPassword::where('reset_key', $resetKey)->first();
            if (!$resetRecord) {
                return ResponseHandle::error($response, 'Invalid reset key', 404);
            }

            if ($resetRecord->expires_at < Carbon::now('Asia/Bangkok')) {
                return ResponseHandle::error($response, 'Reset key has expired', 400);
            }

            if ($resetRecord->is_used) {
                return ResponseHandle::error($response, 'Reset key has already been used', 400);
            }

            $responseData = [
                'email' => $resetRecord->email
            ];

            return ResponseHandle::success($response, $responseData, 'Reset key is valid');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/auth/send/forgot-mail/reset-password
     */
    public function forgotMailResetNewPassword(Request $request, Response $response): Response
    {
        try {
            $body = json_decode((string)$request->getBody(), true);
            $email = $body['email'] ?? null;
            $resetKey = $body['reset_key'] ?? null;
            $newPassword = $body['new_password'] ?? null;

            if (!$email || !$resetKey || !$newPassword) {
                return ResponseHandle::error($response, 'Email, reset key, and new password are required', 400);
            }

            $resetRecord = ForgotPassword::where('email', $email)
                ->where('reset_key', $resetKey)
                ->first();

            if (!$resetRecord) {
                return ResponseHandle::error($response, 'Invalid reset key or email', 404);
            }

            if ($resetRecord->expires_at < Carbon::now('Asia/Bangkok')) {
                return ResponseHandle::error($response, 'Reset key has expired', 400);
            }

            if ($resetRecord->is_used) {
                return ResponseHandle::error($response, 'Reset key has already been used', 400);
            }

            $user = User::where('email', $email)->first();
            if (!$user) {
                return ResponseHandle::error($response, 'User not found', 404);
            }

            $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
            $user->save();

            $resetRecord->is_used = true;
            $resetRecord->save();

            $responseData = [
                'email' => $user->email,
                'reset_status' => 'success',
            ];

            return ResponseHandle::success($response, $responseData, 'Password has been reset successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }
}
