<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserInfo extends Model
{
    protected $table = 'user_info';
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'nickname',
        'phone',
        'avatar_id',
        'avatar_url',
        'created_at',
        'updated_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
