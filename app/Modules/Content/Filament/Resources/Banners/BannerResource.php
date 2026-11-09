<?php

declare(strict_types=1);

namespace App\Modules\Content\Filament\Resources\Banners;

use App\Modules\Catalog\Queries\CatalogLinks;
use App\Modules\Content\Actions\DeleteBanner;
use App\Modules\Content\Enums\BannerLinkType;
use App\Modules\Content\Filament\Resources\Banners\Pages\CreateBanner;
use App\Modules\Content\Filament\Resources\Banners\Pages\EditBanner;
use App\Modules\Content\Filament\Resources\Banners\Pages\ListBanners;
use App\Modules\Content\Models\Banner;
use App\Modules\Media\Filament\Tables\ImageThumbnail;
use App\Modules\Shared\Support\PersianDigits;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * The banner library («بنرها», roadmap D3.6, S13b). The admin places banners in home slider widgets; the
 * «جایگاه بنرها» admin page for the page-top places was removed at the owner's request (2026-11-07).
 */
final class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'محتوا';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'بنر';

    protected static ?string $pluralModelLabel = 'بنرها';

    protected static ?string $recordTitleAttribute = Banner::COLUMN_TITLE;

    public static function form(Schema $schema): Schema
    {
        return BannerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('placements'))
            ->columns([
                ImageThumbnail::make(Banner::IMAGE_MOBILE, 'banner_mobile'),
                TextColumn::make(Banner::COLUMN_TITLE)->label('عنوان')->searchable()->wrap(),
                TextColumn::make(Banner::COLUMN_LINK_TYPE)
                    ->label('مقصد')
                    ->formatStateUsing(fn (BannerLinkType $state): string => $state->label())
                    ->description(fn (Banner $record): ?string => self::targetLabel($record)),
                IconColumn::make(Banner::COLUMN_IS_ACTIVE)->label('فعال')->boolean(),
                IconColumn::make('scheduled')
                    ->label('زمان‌بندی')
                    // A clock when a schedule is set; nothing otherwise (a false state would show a red cross).
                    ->state(fn (Banner $record): ?bool => $record->getStartsAt() instanceof Carbon || $record->getEndsAt() instanceof Carbon ? true : null)
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedClock)
                    ->trueColor('gray'),
                TextColumn::make('placements_count')
                    ->label('جایگاه‌ها')
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'هیچ‌جا' : PersianDigits::toPersian($state)),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([EditAction::make(), self::deleteAction(), RestoreAction::make()]);
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->modalDescription('بنر از همه جایگاه‌هایش برداشته می‌شود و به سطل زباله می‌رود.')
            ->using(function (Banner $record, DeleteBanner $deleteBanner): bool {
                $deleteBanner($record);

                return true;
            });
    }

    /**
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBanners::route('/'),
            'create' => CreateBanner::route('/create'),
            'edit' => EditBanner::route('/{record}/edit'),
        ];
    }

    private static function targetLabel(Banner $banner): ?string
    {
        $type = $banner->getLinkType();

        return match (true) {
            $type === BannerLinkType::External => $banner->getExternalUrl(),
            $type->needsItem() && $banner->getLinkId() !== null => app(CatalogLinks::class)->label($type->value, $banner->getLinkId()) ?? 'حذف‌شده',
            default => null,
        };
    }
}
