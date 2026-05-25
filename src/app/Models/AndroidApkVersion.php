<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AndroidApkVersion extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function booted()
    {
        static::creating(function ($apkVersion) {
            $apkVersion->uid = str_unique();
        });
    }
}
