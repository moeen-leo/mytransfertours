<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['value' => 'json'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("setting:{$key}", fn () => static::where('key', $key)->value('value')) ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting:{$key}");
    }
}
