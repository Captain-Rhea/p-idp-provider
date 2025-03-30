<?php

namespace App\Controllers;

use Exception;
use App\Helpers\JWTHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Helpers\ResponseHandle;
use App\Helpers\VerifyUserStatus;
use App\Models\User;
use App\Helpers\LoginTransactionHandle;
use App\Models\ForgotPassword;
use App\Models\LoginTransaction;
use App\Models\Otps;
use App\Utils\TokenJWTUtils;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Illuminate\Support\Carbon;

class AuthController
{
    /**
     * POST /v1/auth/login
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

            if (!$user) {
                return ResponseHandle::error($response, 'Invalid email or password', 404);
            }

            if (!password_verify($password, $user->password)) {
                LoginTransactionHandle::logTransaction(
                    $user->user_id,
                    'failed',
                    $request->getServerParams()['REMOTE_ADDR'],
                    $request->getHeaderLine('User-Agent')
                );
                return ResponseHandle::error($response, 'Invalid email or password', 404);
            }

            $statusCheckResponse = VerifyUserStatus::check($user->status_id, $response);
            if ($statusCheckResponse) {
                return $statusCheckResponse;
            }

            $roles = $user->roles()->pluck('role_id')->toArray();

            $tokenExp = 60 * 60 * 24 * 7;
            $token = TokenJWTUtils::generateToken([
                'user_id' => $user->user_id,
                'email' => $user->email,
                'role' => $roles[0]
            ], $tokenExp);

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
     * GET /v1/auth/is-login
     */
    public function isLogin(Request $request, Response $response): Response
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

            $tokenDecode = TokenJWTUtils::decodeToken($token);
            $expirationTime = Carbon::createFromTimestamp($tokenDecode['exp']);
            if (Carbon::now('Asia/Bangkok')->diffInDays($expirationTime, false) <= 2) {
                $tokenExp = 60 * 60 * 24 * 7;
                $newToken = TokenJWTUtils::generateToken([
                    'user_id' => $tokenDecode['user_id'],
                    'email' => $tokenDecode['email'],
                    'role' => $tokenDecode['role']
                ], $tokenExp);

                LoginTransactionHandle::logTransaction(
                    $user->user_id,
                    'success',
                    $request->getServerParams()['REMOTE_ADDR'],
                    $request->getHeaderLine('User-Agent')
                );

                return ResponseHandle::success($response, ['token' => $newToken], 'Login successful');
            }

            return ResponseHandle::success($response, ['token' => $token], 'Login successful');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * GET /v1/auth/verify-token
     */
    public function verifyToken(Request $request, Response $response): Response
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

            $tokenDecode = TokenJWTUtils::decodeToken($token);

            return ResponseHandle::success($response, $tokenDecode, 'Verify successful');
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
            $userId = $body['user_id'] ?? null;
            $newPassword = $body['new_password'] ?? null;

            if (!$userId || !$newPassword) {
                return ResponseHandle::error($response, 'User ID and new password are required', 400);
            }

            $user = User::where('user_id', $userId)->first();
            if (!$user) {
                return ResponseHandle::error($response, 'User not found', 404);
            }

            $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
            $user->save();

            return ResponseHandle::success($response, null, 'Password reset successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * GET /v1/auth/forgot-password
     */
    public function getForgotPasswords(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $page = $queryParams['page'] ?? 1;
            $perPage = $queryParams['per_page'] ?? 10;
            $recipientEmail = $queryParams['recipient_email'] ?? null;
            $isUsed = $queryParams['is_used'] ?? null;
            $startDate = $queryParams['start_date'] ?? null;
            $endDate = $queryParams['end_date'] ?? null;

            $query = ForgotPassword::query();

            // Apply filters
            if ($recipientEmail) {
                $query->where('recipient_email', 'LIKE', '%' . $recipientEmail . '%');
            }

            if ($isUsed) {
                $query->where('is_used', $isUsed);
            }

            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            } elseif ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            } elseif ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            // Paginate results
            $forgotPasswords = $query->paginate($perPage, ['*'], 'page', $page);

            $formattedData = collect($forgotPasswords->items())->map(function ($forgot) {
                return [
                    'forgot_id' => $forgot->forgot_id,
                    'recipient_email' => $forgot->recipient_email,
                    'domain' => $forgot->domain,
                    'path' => $forgot->path,
                    'reset_key' => $forgot->reset_key,
                    'is_used' => $forgot->is_used,
                    'forgot_link' => $forgot->domain . '/' . $forgot->path . '?reset_key=' . $forgot->reset_key,
                    'expires_at' => $forgot->expires_at,
                    'created_at' => $forgot->created_at,
                    'updated_at' => $forgot->updated_at,
                ];
            });

            return ResponseHandle::success($response, [
                'pagination' => [
                    'total' => $forgotPasswords->total(),
                    'per_page' => $forgotPasswords->perPage(),
                    'current_page' => $forgotPasswords->currentPage(),
                    'last_page' => $forgotPasswords->lastPage(),
                ],
                'data' => $formattedData,
            ], 'Forgot passwords retrieved successfully');
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
            $recipientEmail = $body['recipient_email'] ?? null;

            if (!$recipientEmail) {
                return ResponseHandle::error($response, 'Recipient email is required', 400);
            }

            $user = User::whereRaw('LOWER(email) = ?', [strtolower($recipientEmail)])->first();
            if (!$user) {
                return ResponseHandle::error($response, 'User not found', 404);
            }

            // Mark previous reset keys as expired
            $forgots = ForgotPassword::where('recipient_email', $recipientEmail)
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
                'recipient_email' => $recipientEmail,
                'domain' => $_ENV['FRONT_URL'],
                'path' => $_ENV['FRONT_RESET_PATH'],
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

            $companyName = $_ENV['EMAIL_COMPANY_NAME'] ?? 'Company Name';
            $companyLogo = $_ENV['EMAIL_COMPANY_LOGO'] ?? '';
            $frontendPath = $_ENV['FRONT_URL'] . "/" . $_ENV['FRONT_RESET_PATH'];
            $emailBaseColor = $_ENV['EMAIL_BASE_COLOR'] ?? '#0B99FF';
            $templateContent = file_get_contents($templatePath);
            $emailBody = str_replace(
                [
                    '{{frontend_path}}',
                    '{{reset_key}}',
                    '{{recipient_email}}',
                    '{{company_name}}',
                    '{{company_logo}}',
                    '{{base_color}}',
                ],
                [
                    $frontendPath,
                    $resetKey,
                    $recipientEmail,
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
            $mailer->setFrom($_ENV['SMTP_USERNAME'], $_ENV['EMAIL_FROM_NAME'] ?? 'Support');
            $mailer->addAddress($recipientEmail);

            // Email Content
            $mailer->isHTML(true);
            $mailer->Subject = 'Password Reset Request';
            $mailer->Body = $emailBody;
            $mailer->AltBody = "Your reset key is: $resetKey";

            $isSendEnabled = filter_var($_ENV['SEND_STATUS'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($isSendEnabled) {
                // Send Email
                $mailer->send();
            }

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
                'recipient_email' => $resetRecord->recipient_email,
                'reset_key' => $resetRecord->reset_key,
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
            $recipientEmail = $body['recipient_email'] ?? null;
            $resetKey = $body['reset_key'] ?? null;
            $newPassword = $body['new_password'] ?? null;

            if (!$recipientEmail || !$resetKey || !$newPassword) {
                return ResponseHandle::error($response, 'Email, reset key, and new password are required', 400);
            }

            $resetRecord = ForgotPassword::where('recipient_email', $recipientEmail)
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

            $user = User::whereRaw('LOWER(email) = ?', [strtolower($recipientEmail)])->first();
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

    /**
     * GET /v1/auth/transaction/login
     */
    public function getLoginTransaction(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $page = (int)($queryParams['page'] ?? 1);
            $perPage = (int)($queryParams['per_page'] ?? 10);
            $userId = $queryParams['user_id'] ?? null;
            $startDate = $queryParams['start_date'] ?? null;
            $endDate = $queryParams['end_date'] ?? null;

            $query = LoginTransaction::query();

            if ($userId) {
                $query->where('user_id', $userId);
            }

            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            } elseif ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            } elseif ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            $query->orderBy('created_at', 'desc');

            $transactions = $query->paginate($perPage, ['*'], 'page', $page);

            $formattedData = collect($transactions->items())->map(function ($transaction) {
                return [
                    'transaction_id' => $transaction->id,
                    'user_id' => $transaction->user_id,
                    'status' => $transaction->status,
                    'ip_address' => $transaction->ip_address,
                    'user_agent' => $transaction->user_agent,
                    'created_at' => $transaction->created_at,
                    'user' => $transaction->user ? [
                        'user_id' => $transaction->user->user_id,
                        'email' => $transaction->user->email
                    ] : null,
                ];
            });

            return ResponseHandle::success($response, [
                'pagination' => [
                    'total' => $transactions->total(),
                    'per_page' => $transactions->perPage(),
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                ],
                'data' => $formattedData,
            ], 'Login Transaction list retrieved successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/auth/send/forgot-otp/reset-password
     */
    public function forgotMailResetNewPasswordByOTP(Request $request, Response $response): Response
    {
        try {
            $body = json_decode((string)$request->getBody(), true);
            $recipientEmail = $body['recipient_email'] ?? null;
            $newPassword = $body['new_password'] ?? null;
            $otpRef = $body['otp_ref'] ?? null;
            $otpCode = $body['otp_code'] ?? null;

            if (!$recipientEmail || !$newPassword || !$otpRef || !$otpCode) {
                return ResponseHandle::error($response, 'Request body are required', 400);
            }

            $resetRecord = Otps::where('recipient_email', $recipientEmail)
                ->where('ref', $otpRef)
                ->where('otp_code', $otpCode)
                ->where('is_used', true)
                ->first();

            if (!$resetRecord) {
                return ResponseHandle::error($response, 'Invalid reset record', 404);
            }

            $user = User::whereRaw('LOWER(email) = ?', [strtolower($recipientEmail)])->first();
            if (!$user) {
                return ResponseHandle::error($response, 'User not found', 404);
            }

            $user->password = password_hash($newPassword, PASSWORD_DEFAULT);
            $user->save();

            return ResponseHandle::success($response, [], 'Password has been reset successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }
}
