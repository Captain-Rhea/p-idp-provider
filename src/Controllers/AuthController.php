<?php

namespace App\Controllers;

use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Helpers\ResponseHandle;
use App\Helpers\VerifyUserStatus;
use App\Models\User;
use App\Models\UserInfo;
use App\Utils\TokenUtils;
use App\Helpers\LoginTransactionHandle;
use App\Models\OtpTransaction;

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

            $statusCheckResponse = VerifyUserStatus::check($user->status, $response);
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
     * POST /v1/auth/register - Register a new user
     */
    public function register(Request $request, Response $response): Response
    {
        try {
            $body = json_decode((string)$request->getBody(), true);

            $email = isset($body['email']) ? strtolower(trim($body['email'])) : null;
            $password = isset($body['password']) ? trim($body['password']) : null;
            $firstName = isset($body['first_name']) ? ucfirst(strtolower(trim($body['first_name']))) : null;
            $lastName = isset($body['last_name']) ? ucfirst(strtolower(trim($body['last_name']))) : null;
            $nickname = isset($body['nickname']) ? trim($body['nickname']) : null;
            $phone = isset($body['phone']) && trim($body['phone']) !== '' ? trim($body['phone']) : null;

            if (!$email || !$password || !$firstName || !$lastName || !$nickname) {
                return ResponseHandle::error($response, 'Email, password, first name, last name, and nickname are required', 400);
            }

            if (User::whereRaw('LOWER(email) = ?', [strtolower($email)])->exists()) {
                return ResponseHandle::error($response, 'Email already exists', 400);
            }

            if (UserInfo::whereRaw('LOWER(nickname) = ?', [strtolower($nickname)])->exists()) {
                return ResponseHandle::error($response, 'Nickname already exists', 400);
            }

            $user = User::create([
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'status' => 2,
            ]);

            $user->userInfo()->create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'nickname' => $nickname,
                'phone' => $phone,
            ]);

            return ResponseHandle::success($response, ['user_id' => $user->user_id], 'Registration successful', 201);
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/auth/email-checking
     */
    public function emailChecking(Request $request, Response $response): Response
    {
        try {
            $body = json_decode((string)$request->getBody(), true);
            $email = $body['email'] ?? null;

            if (!$email) {
                return ResponseHandle::error($response, 'Email is required', 400);
            }

            if (User::whereRaw('LOWER(email) = ?', [strtolower($email)])->exists()) {
                return ResponseHandle::error($response, 'Email already exists', 400);
            }

            return ResponseHandle::success($response, [], 'Email already in use', 200);
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/auth/nickname-checking
     */
    public function nicknameChecking(Request $request, Response $response): Response
    {
        try {
            $body = json_decode((string)$request->getBody(), true);
            $nickname = $body['nickname'] ?? null;

            if (!$nickname) {
                return ResponseHandle::error($response, 'Email is required', 400);
            }

            if (UserInfo::whereRaw('LOWER(nickname) = ?', [strtolower($nickname)])->exists()) {
                return ResponseHandle::error($response, 'Nickname already exists', 400);
            }

            return ResponseHandle::success($response, [], 'Nickname already in use', 200);
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
     * POST /v1/auth/forget-password
     */
    public function forgotPassword(Request $request, Response $response): Response
    {
        try {
            $body = json_decode((string)$request->getBody(), true);
            $email = $body['email'] ?? null;
            $refCode = $body['ref_code'] ?? null;
            $otpCode = $body['otp_code'] ?? null;
            $purpose = $body['purpose'] ?? null;
            $newPassword = $body['new_password'] ?? null;

            if (!$email || !$refCode || !$otpCode || !$purpose || !$newPassword) {
                return ResponseHandle::error($response, 'All fields are required', 400);
            }

            $otp = OtpTransaction::where('email', strtolower($email))
                ->where('ref_code', $refCode)
                ->where('otp_code', $otpCode)
                ->where('purpose', $purpose)
                ->where('is_used', true)
                ->first();

            if (!$otp) {
                return ResponseHandle::error($response, 'Invalid or unverified OTP', 400);
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
}
