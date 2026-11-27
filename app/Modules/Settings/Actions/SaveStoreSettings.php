<?php

declare(strict_types=1);

namespace App\Modules\Settings\Actions;

use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Queries\StoreSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Saves store settings (only known keys) and clears their cache. Each changed key is one audit entry. Secrets (API
 * keys, S37) are encrypted, and an empty one keeps the saved value.
 */
final class SaveStoreSettings
{
    /**
     * @param  array<string, mixed>  $values  key => value, keys from StoreSettings::DEFAULTS
     */
    public function __invoke(array $values): void
    {
        DB::transaction(function () use ($values): void {
            foreach (array_intersect_key($values, StoreSettings::DEFAULTS) as $key => $value) {
                if (in_array($key, StoreSettings::SECRET_KEYS, true)) {
                    if (trim((string) $value) === '') {
                        continue;
                    }

                    $value = Crypt::encryptString(trim((string) $value));
                }

                (Setting::query()->where(Setting::COLUMN_KEY, $key)->first() ?? (new Setting)->setSettingKey($key))
                    ->setValue($value)
                    ->save();
            }
        });

        Cache::forget(StoreSettings::CACHE_KEY);
    }
}
