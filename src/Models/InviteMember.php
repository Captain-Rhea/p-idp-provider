<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InviteMember extends Model
{
    protected $table = 'invite_member';
    protected $fillable = [
        'inviter_id',
        'email',
        'status',
        'ref_code',
        'expires_at',
        'created_at',
        'updated_at'
    ];

    public function inviter()
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }
}
