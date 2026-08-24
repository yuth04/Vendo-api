<?php

namespace App\Services;

use App\Models\Setting;

class SettingService
{
    public function get($key, $default = null)
    {
        return Setting::where('key', $key)->value('value') ?? $default;
    }

    public function set($key, $value)
    {
        Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}