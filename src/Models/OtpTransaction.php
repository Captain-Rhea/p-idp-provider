<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class OtpTransaction extends Model
{
    protected $table = 'otp_transaction';
    public $timestamps = false;
    protected $fillable = [
        'user_id',
        'email',
        'ref_code',
        'otp_code',
        'purpose',
        'is_used',
        'expires_at',
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function scopeValid($query)
    {
        return $query->where('is_used', false)
            ->where('expires_at', '>', Carbon::now());
    }

    public function markAsUsed(): bool
    {
        $this->is_used = true;
        return $this->save();
    }
}
