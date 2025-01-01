<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserInfoTranslation extends Model
{
    protected $table = 'user_info_translation';
    public $timestamps = true;
    protected $fillable = [
        'user_id',
        'language_code',
        'first_name',
        'last_name',
        'nickname'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
