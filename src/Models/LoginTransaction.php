<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class LoginTransaction extends Model
{
    protected $table = 'login_transaction';
    public $timestamps = false;
    protected $fillable = [
        'user_id',
        'status',
        'ip_address',
        'user_agent'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = Carbon::now('Asia/Bangkok');
        });
    }
}
