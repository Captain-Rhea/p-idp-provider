<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ForgotPassword extends Model
{
    protected $table = 'forgot_password';
    protected $primaryKey = 'forgot_id';
    public $incrementing = true;
    public $timestamps = true;
    protected $fillable = [
        'email',
        'reset_key',
        'is_used',
        'expires_at'
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
