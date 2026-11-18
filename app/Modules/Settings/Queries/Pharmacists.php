<?php

declare(strict_types=1);

namespace App\Modules\Settings\Queries;

use App\Modules\Settings\Models\Pharmacist;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The store's pharmacists (roadmap S30). Other modules read them only through this query: the pharmacist in charge's
 * name and council number are cached, because every product page shows them.
 */
final readonly class Pharmacists
{
    private const string CACHE_KEY = 'settings:responsible-pharmacist';

    /**
     * @return array{name: string, licenseNumber: string}|null
     */
    public function responsible(): ?array
    {
        /** @var array{name: string, licenseNumber: string}|false $pharmacist */
        $pharmacist = Cache::rememberForever(self::CACHE_KEY, function (): array|false {
            $pharmacist = Pharmacist::query()->responsible()->first();

            return $pharmacist instanceof Pharmacist ? ['name' => $pharmacist->getFullName(), 'licenseNumber' => $pharmacist->getLicenseNumber()] : false;
        });

        return $pharmacist === false ? null : $pharmacist;
    }

    /**
     * Everyone for /pharmacist: the pharmacist in charge first, then the others in the admin's order.
     *
     * @return Collection<int, Pharmacist>
     */
    public function all(): Collection
    {
        return Pharmacist::query()
            ->with('media')
            ->orderByDesc(Pharmacist::COLUMN_IS_RESPONSIBLE)
            ->orderBy(Pharmacist::COLUMN_SORT)
            ->orderBy(Pharmacist::COLUMN_ID)
            ->get();
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
