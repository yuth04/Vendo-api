<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class StoreSetting extends Model
{
    protected $fillable = ['key', 'value', 'group', 'type', 'label', 'is_public'];

    protected $casts = [
        'is_public' => 'boolean',
        'created_at' => 'datetime:d-m-Y g:i:s A',
        'updated_at' => 'datetime:d-m-Y g:i:s A',
    ];

    // Get a single value by key
    public static function get(string $key, string $default = ''): string
    {
        return Cache::rememberForever(
            "setting_{$key}",
            fn() =>
            static::where('key', $key)->value('value') ?? $default
        );
    }

    // Get all settings as key => value
    public static function allKeyed(): array
    {
        return Cache::rememberForever(
            'store_settings',
            fn() =>
            static::pluck('value', 'key')->toArray()
        );
    }

    // Get settings by group as key => value
    public static function group(string $group): array
    {
        return Cache::rememberForever(
            "store_settings_{$group}",
            fn() =>
            static::where('group', $group)->pluck('value', 'key')->toArray()
        );
    }

    // Auto clear cache on save/delete
    protected static function booted(): void
    {
        static::saved(function ($setting) {
            Cache::forget('store_settings');
            Cache::forget("setting_{$setting->key}");
            Cache::forget("store_settings_{$setting->group}");
        });

        static::deleted(function ($setting) {
            Cache::forget('store_settings');
            Cache::forget("setting_{$setting->key}");
            Cache::forget("store_settings_{$setting->group}");
        });
    }
}