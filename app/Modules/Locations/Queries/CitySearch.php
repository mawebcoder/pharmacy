<?php

declare(strict_types=1);

namespace App\Modules\Locations\Queries;

use App\Modules\Locations\Models\City;
use App\Modules\Shared\Support\PersianNormalizer;
use Illuminate\Database\Eloquent\Builder;

/**
 * City options for admin selects in other modules (e.g. a supplier's city): id => «شهر، استان».
 */
final readonly class CitySearch
{
    private const int LIMIT = 30;

    /**
     * @return array<int, string>
     */
    public function options(string $search): array
    {
        $term = PersianNormalizer::letters(trim($search));

        return $this->labelled(City::query()
            ->where(fn (Builder $match) => $match
                ->where(City::COLUMN_NAME_FA, 'like', "%{$term}%")
                ->orWhereHas('province', fn (Builder $provinces) => $provinces->where('name_fa', 'like', "%{$term}%")))
            ->orderBy(City::COLUMN_NAME_FA)
            ->limit(self::LIMIT));
    }

    public function label(int $cityId): ?string
    {
        return $this->labelled(City::query()->whereKey($cityId))[$cityId] ?? null;
    }

    /**
     * @param  Builder<City>  $query
     * @return array<int, string>
     */
    private function labelled(Builder $query): array
    {
        $options = [];

        foreach ($query->with('province')->get() as $city) {
            $options[$city->getId()] = $city->getNameFa().'، '.$city->getProvince()->getNameFa();
        }

        return $options;
    }
}
