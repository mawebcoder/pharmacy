<?php

declare(strict_types=1);

namespace App\Modules\Settings\Actions;

use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Queries\StoreSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Saves store settings (only known keys) and clears their cache. Each changed key is one audit entry.
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
                (Setting::query()->where(Setting::COLUMN_KEY, $key)->first() ?? (new Setting)->setSettingKey($key))
                    ->setValue($value)
                    ->save();
            }
        });

        Cache::forget(StoreSettings::CACHE_KEY);
    }
}
