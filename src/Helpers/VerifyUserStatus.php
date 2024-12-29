<?php

namespace App\Helpers;

use Psr\Http\Message\ResponseInterface as Response;
use App\Utils\UserStatusUtils;

class VerifyUserStatus
{
    /**
     * ตรวจสอบสถานะของผู้ใช้
     *
     * @param int $status สถานะของผู้ใช้
     * @param Response $response อินสแตนซ์ Response สำหรับส่งกลับ
     * @return Response|null Response หากสถานะไม่ถูกต้อง, หรือ null หากสถานะใช้งานได้
     */
    public static function check(int $status, Response $response): ?Response
    {
        switch ($status) {
            case UserStatusUtils::DELETED:
                return ResponseHandle::error($response, 'Account not found', 404);
            case UserStatusUtils::SUSPENDED:
                return ResponseHandle::error($response, 'Your account is temporarily suspended', 401);
            case UserStatusUtils::PENDING:
                return ResponseHandle::error($response, 'Your account is pending approval', 401);
            case UserStatusUtils::ACTIVE:
                return null;
            default:
                return ResponseHandle::error($response, 'Invalid user status', 400);
        }
    }
}
