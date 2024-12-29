<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginTransaction extends Model
{
    protected $table = 'login_transaction';
    public $timestamps = false;
    protected $fillable = [
        'user_id',
        'status',
        'ip_address',
        'user_agent',
        'created_at',
    ];
}
