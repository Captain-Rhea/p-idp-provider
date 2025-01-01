<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InviteMember extends Model
{
    protected $table = 'invite_member';
    public $timestamps = true;
    protected $fillable = [
        'inviter_id',
        'email',
        'status_id',
        'ref_code',
        'role_id',
        'expires_at'
    ];

    public function inviter()
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }
}
