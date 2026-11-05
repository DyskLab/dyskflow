<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $guarded = [];

    private static ?array $loaded = null;

    public static function read(string $key, mixed $default = null): mixed
    {
        self::$loaded ??= self::query()->pluck('value', 'key')->all();

        return self::$loaded[$key] ?? $default;
    }

    public static function write(array $values): void
    {
        foreach ($values as $key => $value) {
            self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
        self::$loaded = null;
    }
}
