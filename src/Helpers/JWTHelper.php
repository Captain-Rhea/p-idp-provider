<?php

namespace App\Helpers;

use App\Models\User;
use App\Utils\TokenJWTUtils;

class JWTHelper
{
    /**
     * Get user from token
     *
     * @param string $token
     * @return User|null
     */
    public static function getUser(string $token): ?User
    {
        if (empty($token)) {
            return null;
        }

        // ตรวจสอบว่าโทเค็นถูกต้อง
        if (!TokenJWTUtils::isValidToken($token)) {
            return null;
        }

        // ถอดรหัสโทเค็น
        $decoded = TokenJWTUtils::decodeToken($token);

        // ตรวจสอบว่ามี user_id ในโทเค็นหรือไม่
        if (empty($decoded['user_id'])) {
            return null;
        }

        // ค้นหาผู้ใช้ในฐานข้อมูล
        return User::where('user_id', $decoded['user_id'])->first();
    }
}
