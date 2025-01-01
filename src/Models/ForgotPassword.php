<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
