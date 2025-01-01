<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'user_id';
    public $incrementing = true;
    protected $fillable = [
        'email',
        'password',
        'status_id',
        'avatar_id',
        'avatar_url'
    ];

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role', 'user_id', 'role_id');
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_permission', 'user_id', 'permission_id');
    }

    public function userInfo()
    {
        return $this->hasOne(UserInfo::class, 'user_id');
    }

    public function userInfoTranslation()
    {
        return $this->hasMany(UserInfoTranslation::class, 'user_id');
    }
}
