<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Otps extends Model
{
    protected $table = 'otps';
    public $timestamps = false;
    protected $fillable = [
        'recipient_email',
        'otp_code',
        'type',
        'ref',
        'expires_at',
        'is_used',
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = Carbon::now('Asia/Bangkok');
            $model->updated_at = Carbon::now('Asia/Bangkok');
        });

        static::updating(function ($model) {
            $model->updated_at = Carbon::now('Asia/Bangkok');
        });
    }
}
