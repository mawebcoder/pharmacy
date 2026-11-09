<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Forms;

use App\Modules\Catalog\Queries\AdminOptions;
use Closure;
use Filament\Forms\Components\Select;

/**
 * Admin selects whose options show each item's round image (S13b, decided 2026-11-07).
 * - images(): a select over a known list (brands, category paths, tags), searched by the plain label.
 * - skus(): pick products by SKU: one option per SKU with the product name and, below it, the SKU title, code and
 *   id; searched by product name, SKU code or id. The state holds variant ids (AdminOptions::productIdsOfSkus()).
 */
final class ImageSelect
{
    /**
     * @param  Closure(): array<int, string>  $labels  id => plain label; Filament injects $record and the like
     */
    public static function images(Select $select, string $type, Closure $labels): Select
    {
        $options = app(AdminOptions::class);
        /** @var Closure(): array<int, string> $labels */
        $labels = fn (): array => (array) $select->evaluate($labels);

        return $select
            ->allowHtml()
            ->searchable()
            ->options(fn (): array => $options->withImages($type, $labels()))
            ->getSearchResultsUsing(fn (string $search): array => $options->withImages($type, array_filter(
                $labels(),
                fn (string $label): bool => mb_stripos($label, trim($search)) !== false,
            )))
            ->getOptionLabelUsing(fn (int|string $value): ?string => $options->withImages($type, $labels())[(int) $value] ?? null)
            ->getOptionLabelsUsing(fn (array $values): array => array_intersect_key($options->withImages($type, $labels()), array_flip(array_map(intval(...), $values))));
    }

    public static function skus(Select $select): Select
    {
        $options = app(AdminOptions::class);

        return $select
            ->allowHtml()
            ->searchable()
            ->helperText('با نام محصول، کد SKU یا شناسه جستجو کنید.')
            ->getSearchResultsUsing(fn (string $search): array => $options->search('sku', $search))
            ->getOptionLabelUsing(fn (int|string $value): ?string => $options->labels('sku', [$value])[(int) $value] ?? null)
            ->getOptionLabelsUsing(fn (array $values): array => $options->labels('sku', array_values($values)));
    }
}
