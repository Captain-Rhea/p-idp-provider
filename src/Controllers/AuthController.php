<?php

namespace App\Controllers;

use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Helpers\ResponseHandle;
use App\Models\User;
use App\Utils\TokenUtils;

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
                return ResponseHandle::error($response, 'Invalid email or password', 401);
            }

            $roles = $user->roles()->pluck('name')->toArray();

            $token = TokenUtils::generateToken([
                'user_id' => $user->user_id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'role' => $roles
            ], 60 * 60 * 48);

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
            $email = $body['email'] ?? null;
            $password = $body['password'] ?? null;
            $firstName = $body['first_name'] ?? null;
            $lastName = $body['last_name'] ?? null;
            $nickname = $body['nickname'] ?? null;

            if (!$email || !$password || !$firstName || !$lastName || $nickname) {
                return ResponseHandle::error($response, 'Email, password, first name, and last name are required', 400);
            }

            if (User::where('email', $email)->exists()) {
                return ResponseHandle::error($response, 'Email already exists', 400);
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
                'phone' => $body['phone'] ?? null
            ]);

            return ResponseHandle::success($response, ['user_id' => $user->user_id], 'Registration successful', 201);
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }
}
