<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ApiConnectionModel extends Model
{
    protected $table = 'api_connection';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'connection_name',
        'connection_key',
    ];

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
