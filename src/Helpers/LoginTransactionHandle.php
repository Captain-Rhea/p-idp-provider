<?php

namespace App\Helpers;

use App\Models\LoginTransaction;
use Illuminate\Support\Carbon;

class LoginTransactionHandle
{
    /**
     * Log a login transaction.
     *
     * @param int $userId
     * @param string $status
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return LoginTransaction
     */
    public static function logTransaction(int $userId, string $status, ?string $ipAddress = null, ?string $userAgent = null): LoginTransaction
    {
        return LoginTransaction::create([
            'user_id' => $userId,
            'status' => $status,
            'ip_address' => $ipAddress ?? 'Unknown',
            'user_agent' => $userAgent ?? 'Unknown'
        ]);
    }

    /**
     * Get login transactions for a user.
     *
     * @param int $userId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getTransactionsForUser(int $userId, int $limit = 10)
    {
        return LoginTransaction::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Count failed login attempts for a user within a specific time period.
     *
     * @param int $userId
     * @param int $minutes
     * @return int
     */
    public static function countFailedAttempts(int $userId, int $minutes): int
    {
        $timeThreshold = Carbon::now('Asia/Bangkok')->subMinutes($minutes);

        return LoginTransaction::where('user_id', $userId)
            ->where('status', 'failed')
            ->where('created_at', '>=', $timeThreshold)
            ->count();
    }
}
