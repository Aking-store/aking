<?php

use App\Models\Settings;

if (!function_exists('loadSettings')) {
    /**
     * @return array
     */
    function loadSettings(): array
    {
        return [
            'g2g-cookie' => [
                'active_device_token' => Settings::where('key','active_device_token')->first()->value,
                'refresh_token' => Settings::where('key','refresh_token')->first()->value,
                'YII_CSRF_TOKEN' => Settings::where('key','YII_CSRF_TOKEN')->first()->value,
            ]
        ];
    }
}

if (!function_exists('loadG2GCookie')) {
    /**
     * @return array
     */
    function loadG2GCookie(): array
    {
        return [
            'active_device_token' => Settings::where('key','active_device_token')->first()->value,
            'refresh_token' => Settings::where('key','refresh_token')->first()->value,
            'YII_CSRF_TOKEN' => Settings::where('key','YII_CSRF_TOKEN')->first()->value,
        ];
    }
}

if (!function_exists('getSettingByKey')) {
    /**
     * @param $key
     * @return string
     */
    function getSettingByKey($key): string
    {
        return Settings::where($key,'active_device_token')->first()?->value;
    }
}
