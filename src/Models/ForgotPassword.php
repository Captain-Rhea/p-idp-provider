<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ForgotPassword extends Model
{
    protected $table = 'forgot_password';
    public $timestamps = false;
    protected $primaryKey = 'forgot_id';
    protected $fillable = [
        'email',
        'reset_key',
        'is_used',
        'sent_at',
        'expires_at',
    ];
}
