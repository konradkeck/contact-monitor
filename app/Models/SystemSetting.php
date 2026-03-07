<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value_json'];
    protected $casts    = ['value_json' => 'array'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value_json') ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value_json' => $value]);
    }
}
