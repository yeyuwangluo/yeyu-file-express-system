<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = [];

    public static function valueFor(string $group, string $key, $default = null)
    {
        $setting = static::cachedValues()[$group][$key] ?? null;

        if (! $setting) {
            return $default;
        }

        switch ($setting['type']) {
            case 'int':
                return (int) $setting['value'];
            case 'bool':
                return filter_var($setting['value'], FILTER_VALIDATE_BOOLEAN);
            case 'json':
                return json_decode((string) $setting['value'], true) ?? $default;
            default:
                return $setting['value'];
        }
    }

    public static function putDefault(string $group, string $key, $value, string $type = 'string', ?string $description = null): void
    {
        static::query()->firstOrCreate(
            ['group' => $group, 'key' => $key],
            [
                'value' => $type === 'json' ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value,
                'type' => $type,
                'description' => $description,
            ],
        );
    }

    public static function setValue(string $group, string $key, $value, string $type = 'string', ?string $description = null): void
    {
        static::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            [
                'value' => $type === 'json' ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value,
                'type' => $type,
                'description' => $description,
            ],
        );
    }

    private static function cachedValues(): array
    {
        if (! app()->runningInConsole() && app()->bound('request')) {
            $request = request();
            $cacheKey = 'yeyu_file_express_settings';

            if ($request->attributes->has($cacheKey)) {
                return $request->attributes->get($cacheKey);
            }

            $values = static::loadValues();
            $request->attributes->set($cacheKey, $values);

            return $values;
        }

        return static::loadValues();
    }

    private static function loadValues(): array
    {
        $values = [];

        static::query()
            ->get(['group', 'key', 'value', 'type'])
            ->each(function (Setting $setting) use (&$values): void {
                $values[$setting->group][$setting->key] = [
                    'value' => $setting->value,
                    'type' => $setting->type,
                ];
            });

        return $values;
    }
}
