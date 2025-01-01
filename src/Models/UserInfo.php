<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class UserInfo extends Model
{
    protected $table = 'user_info';
    public $timestamps = true;
    protected $fillable = [
        'user_id',
        'phone'
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
            $model->updated_at = Carbon::now('Asia/Bangkok');
        });

        static::updating(function ($model) {
            $model->updated_at = Carbon::now('Asia/Bangkok');
        });
    }
}
