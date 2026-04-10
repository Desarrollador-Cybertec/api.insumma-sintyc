<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    protected static array $cache = [];

    /**
     * Preload all settings into memory (single query).
     */
    public static function preload(): void
    {
        static::$cache = [];
        foreach (static::all() as $setting) {
            static::$cache[$setting->key] = $setting->castValue();
        }
    }

    /**
     * Flush the in-memory cache.
     */
    public static function flushCache(): void
    {
        static::$cache = [];
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::$cache = []);
        static::deleted(fn () => static::$cache = []);
    }

    /**
     * Get a setting value by key, with optional default.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, static::$cache)) {
            return static::$cache[$key];
        }

        $setting = static::where('key', $key)->first();

        if (!$setting) {
            static::$cache[$key] = $default;
            return $default;
        }

        $value = $setting->castValue();
        static::$cache[$key] = $value;
        return $value;
    }

    /**
     * Set a setting value by key.
     */
    public static function setValue(string $key, mixed $value): void
    {
        $setting = static::where('key', $key)->first();

        if ($setting) {
            $setting->update(['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]);
        }
    }

    /**
     * Get all settings for a group.
     */
    public static function getGroup(string $group): array
    {
        return static::where('group', $group)
            ->get()
            ->mapWithKeys(fn (self $s) => [$s->key => $s->castValue()])
            ->toArray();
    }

    /**
     * Cast the stored string value to its declared type.
     */
    public function castValue(): mixed
    {
        return match ($this->type) {
            'boolean' => in_array($this->value, ['1', 'true', 'yes'], true),
            'integer' => (int) $this->value,
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }
}
