<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPermission extends Model
{
    protected $table = 'user_permission';
    public $timestamps = false;
    protected $fillable = [
        'user_id',
        'permission_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
