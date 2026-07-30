<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class AppSettings extends Model
{
    use HasFactory;

    protected $table = 'app_settings';

    protected $fillable = [
        'key',
        'value',
        'setting_type',
        'category',
        'description',
        'is_encrypted',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeByCategory(Builder $query, string $category)
    {
        return $query->where('category', $category);
    }

    public function getValue()
    {
        $value = $this->value;

        if ($this->is_encrypted) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Exception $e) {
                return null;
            }
        }

        if ($this->setting_type === 'array' || $this->setting_type === 'json') {
            return json_decode($value, true);
        }

        return $value;
    }

    public function setValue($value)
    {
        if ($this->setting_type === 'array' || $this->setting_type === 'json') {
            $value = json_encode($value);
        }

        if ($this->is_encrypted) {
            $value = Crypt::encryptString((string)$value);
        }

        $this->value = $value;
        return $this;
    }

    public static function getSetting(string $key, $default = null)
    {
        try {
            // Settings are read by some public endpoints during the initial
            // install, before all migrations have necessarily completed.
            if (! Schema::hasTable((new static)->getTable())) {
                return $default;
            }

            $setting = static::where('key', $key)->first();
        } catch (\Throwable $e) {
            return $default;
        }

        if (!$setting) {
            return $default;
        }

        return $setting->getValue();
    }

    public static function setSetting(string $key, $value, array $attributes = [])
    {
        $setting = static::firstOrNew(['key' => $key]);
        $setting->fill($attributes);
        $setting->setValue($value);
        $setting->save();

        return $setting;
    }
}
