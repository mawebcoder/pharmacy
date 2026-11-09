<?php

declare(strict_types=1);

namespace App\Modules\Content\Filament\Forms;

use App\Modules\Catalog\Support\AdminOptionLabel;
use App\Modules\Content\Enums\BannerPlace;
use App\Modules\Content\Models\Banner;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;

/**
 * The banners of one place, in order (drag to reorder, at most 6), picked from the library with their mobile artwork
 * as a round preview. Used in home slider widgets. The state is a list of banner ids.
 */
final class PlaceBannersField
{
    public static function make(string $name = 'banner_ids'): Repeater
    {
        return Repeater::make($name)
            ->label('بنرها (به ترتیب نمایش)')
            ->simple(
                Select::make('banner_id')
                    ->label('بنر')
                    ->allowHtml()
                    ->searchable()
                    ->required()
                    ->options(fn (): array => self::options())
                    ->getSearchResultsUsing(fn (string $search): array => self::options($search)),
            )
            ->reorderable()
            ->maxItems(BannerPlace::MAX_BANNERS)
            ->defaultItems(0)
            ->addActionLabel('افزودن بنر')
            ->helperText('حداکثر ۶ بنر؛ با بیش از یکی، اسلایدر نمایش داده می‌شود. بنرهای غیرفعال یا خارج از زمان‌بندی نمایش داده نمی‌شوند.');
    }

    /**
     * @return array<int, string>
     */
    private static function options(string $search = ''): array
    {
        $options = [];
        $banners = Banner::query()
            ->with('media')
            ->when($search !== '', fn ($query) => $query->where(Banner::COLUMN_TITLE, 'like', '%'.trim($search).'%'))
            ->latest()
            ->limit(50)
            ->get();

        foreach ($banners as $banner) {
            $options[$banner->getId()] = AdminOptionLabel::html($banner->getMobileImage(), 480, $banner->getTitle(), $banner->isActive() ? null : 'غیرفعال');
        }

        return $options;
    }
}
