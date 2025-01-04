<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class InviteMember extends Model
{
    protected $table = 'invite_member';
    public $timestamps = true;
    protected $fillable = [
        'inviter_id',
        'status_id',
        'recipient_email',
        'domain',
        'path',
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
