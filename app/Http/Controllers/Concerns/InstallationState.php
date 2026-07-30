<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AppSettings;
use Illuminate\Support\Facades\Schema;

trait InstallationState
{
    /**
     * Determine whether the application has completed installation.
     *
     * The database setting is authoritative once the settings table exists.
     * APP_INSTALLED remains a bootstrap fallback for the first request before
     * the database has been configured.
     */
    protected function isApplicationInstalled(): bool
    {
        try {
            if (Schema::hasTable('app_settings')) {
                $storedValue = AppSettings::getSetting('app_installed', null);

                if ($storedValue !== null) {
                    return $this->toBoolean($storedValue);
                }
            }
        } catch (\Throwable $e) {
            // Fall back to the environment during initial setup or when the
            // configured database is not available yet.
        }

        return $this->toBoolean(env('APP_INSTALLED', false));
    }

    /**
     * Normalize values coming from .env files and long text database columns.
     */
    protected function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), [
            '1',
            'true',
            'yes',
            'on',
        ], true);
    }
}
