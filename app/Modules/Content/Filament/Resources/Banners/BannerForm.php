<?php

declare(strict_types=1);

namespace App\Modules\Content\Filament\Resources\Banners;

use App\Modules\Content\Filament\Forms\LinkTargetFields;
use App\Modules\Content\Models\Banner;
use App\Modules\Media\Filament\Forms\ImageUpload;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The banner form: name, where it leads (an item picked from the catalog, a list page or an https address), the three
 * artworks and the schedule. Each catalog type has its own picker field; toLinkId() turns them into link_id.
 */
final class BannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('مشخصات')
                    ->columns(2)
                    ->schema([
                        TextInput::make(Banner::COLUMN_TITLE)
                            ->label('عنوان')
                            ->helperText('برای شناسایی در مدیریت و متن جایگزین تصویر.')
                            ->required()
                            ->maxLength(255),
                        Toggle::make(Banner::COLUMN_IS_ACTIVE)->label('فعال')->default(true)->inline(false),
                        DateTimePicker::make(Banner::COLUMN_STARTS_AT)->label('شروع نمایش')->helperText('خالی یعنی از همین حالا.'),
                        DateTimePicker::make(Banner::COLUMN_ENDS_AT)->label('پایان نمایش')->helperText('خالی یعنی بدون پایان.')->after(Banner::COLUMN_STARTS_AT),
                    ]),
                Section::make('مقصد بنر')
                    ->description('با کلیک روی بنر به کجا برود؟ مورد را انتخاب کنید تا با تغییر نامک، پیوند خراب نشود.')
                    ->columns(2)
                    ->schema([
                        ...LinkTargetFields::make(),
                    ]),
                Section::make('تصاویر')
                    ->description('سه طرح جدا برای سه اندازه صفحه؛ متن مهم را در ۷۰٪ میانی تصویر بگذارید. تبلت اختیاری است و نبودش یعنی طرح دسکتاپ.')
                    ->columns(3)
                    ->schema([
                        ImageUpload::make(Banner::IMAGE_DESKTOP, 'banner_desktop')->label('دسکتاپ')->required(),
                        ImageUpload::make(Banner::IMAGE_TABLET, 'banner_tablet')->label('تبلت'),
                        ImageUpload::make(Banner::IMAGE_MOBILE, 'banner_mobile')->label('موبایل')->required(),
                    ]),
            ]);
    }

    /**
     * link_id from the picker of the chosen type (a picked SKU becomes its product).
     *
     * @param  array<string, mixed>  $data
     */
    public static function toLinkId(array $data): ?int
    {
        return LinkTargetFields::toLinkId($data);
    }

    /**
     * Fills the picker of the saved link type (a product shows its default SKU).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withPickers(array $data): array
    {
        return LinkTargetFields::withPickers($data);
    }
}
