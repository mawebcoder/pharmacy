<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Shared\Support\PersianNormalizer;
use Collator;
use Illuminate\Support\Collection;

/**
 * Active brands for the /brands index, sorted by Persian name (one list since 2026-11-27, owner). grouped() still
 * groups them by first letter: Persian letters in alphabet order (آ sits under الف), then Latin letters A–Z, then "#".
 */
final class ActiveBrandsQuery
{
    private const array PERSIAN_ALPHABET = [
        'ا', 'ب', 'پ', 'ت', 'ث', 'ج', 'چ', 'ح', 'خ', 'د', 'ذ', 'ر', 'ز', 'ژ', 'س', 'ش',
        'ص', 'ض', 'ط', 'ظ', 'ع', 'غ', 'ف', 'ق', 'ک', 'گ', 'ل', 'م', 'ن', 'و', 'ه', 'ی',
    ];

    private const string OTHER = '#';

    /**
     * @return list<Brand> active brands sorted by Persian name, with their logos
     */
    public function sorted(): array
    {
        $collator = new Collator('fa');

        $brands = Brand::query()->active()->with('media')->get()->all();
        usort($brands, fn (Brand $a, Brand $b): int => (int) $collator->compare($a->getNameFa(), $b->getNameFa()));

        return $brands;
    }

    /**
     * @return Collection<string, list<Brand>> letter label => brands sorted by Persian name
     */
    public function grouped(): Collection
    {
        $brands = $this->sorted();

        /** @var array<string, list<Brand>> $groups */
        $groups = [];

        foreach ($brands as $brand) {
            $groups[self::letterFor($brand->getNameFa())][] = $brand;
        }

        uksort($groups, fn (string $a, string $b): int => $this->letterRank($a) <=> $this->letterRank($b) ?: strcmp($a, $b));

        return collect($groups);
    }

    /**
     * The index letter for a name: "الف" for names starting with ا or آ, the uppercase letter for Latin names.
     */
    public static function letterFor(string $name): string
    {
        $first = mb_substr(PersianNormalizer::letters(trim($name)), 0, 1);
        $first = in_array($first, ['آ', 'أ', 'إ'], true) ? 'ا' : $first;

        if (in_array($first, self::PERSIAN_ALPHABET, true)) {
            return $first === 'ا' ? 'الف' : $first;
        }

        if (preg_match('/^[a-z]$/i', $first) === 1) {
            return strtoupper($first);
        }

        return self::OTHER;
    }

    private function letterRank(string $letter): int
    {
        if ($letter === self::OTHER) {
            return 1000;
        }

        $persian = array_search($letter === 'الف' ? 'ا' : $letter, self::PERSIAN_ALPHABET, true);

        return $persian === false ? 100 : $persian;
    }
}
