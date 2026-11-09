<?php

declare(strict_types=1);

namespace App\Modules\Content\Filament\Resources\Banners;

use App\Modules\Catalog\Filament\Forms\ImageSelect;
use App\Modules\Catalog\Queries\AdminOptions;
use App\Modules\Content\Enums\BannerLinkType;
use App\Modules\Content\Models\Banner;
use App\Modules\Media\Filament\Forms\ImageUpload;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * The banner form: name, where it leads (an item picked from the catalog, a list page or an https address), the three
 * artworks and the schedule. Each catalog type has its own picker field; toLinkId() turns them into link_id.
 */
final class BannerForm
{
    /**
     * Picker field per catalog link type (products are picked by SKU).
     */
    public const array PICKERS = [
        'category' => 'link_category_id',
        'collection' => 'link_collection_id',
        'brand' => 'link_brand_id',
        'tag' => 'link_tag_id',
        'product' => 'link_sku_id',
    ];

    public static function configure(Schema $schema): Schema
    {
        $options = app(AdminOptions::class);
        $pickers = [];

        foreach (self::PICKERS as $type => $field) {
            $select = Select::make($field)
                ->label('مقصد')
                ->required()
                ->visible(fn (Get $get): bool => self::linkType($get) === BannerLinkType::from($type));
            $pickers[] = $type === 'product' ? ImageSelect::skus($select) : ImageSelect::images($select, $type, fn (): array => $options->names($type));
        }

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
                        Select::make(Banner::COLUMN_LINK_TYPE)
                            ->label('نوع مقصد')
                            ->options(BannerLinkType::options())
                            ->required()
                            ->live(),
                        ...$pickers,
                        TextInput::make(Banner::COLUMN_EXTERNAL_URL)
                            ->label('آدرس بیرونی')
                            ->url()
                            ->startsWith(['https://'])
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->helperText('در برگه جدید باز می‌شود.')
                            ->required()
                            ->visible(fn (Get $get): bool => self::linkType($get) === BannerLinkType::External),
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
        $type = BannerLinkType::tryFrom((string) ($data[Banner::COLUMN_LINK_TYPE] ?? ''));
        $field = $type === null ? null : (self::PICKERS[$type->value] ?? null);
        $value = $field === null ? null : ($data[$field] ?? null);

        if (blank($value)) {
            return null;
        }

        return $type === BannerLinkType::Product
            ? (app(AdminOptions::class)->productIdsOfSkus([(int) $value])[0] ?? null)
            : (int) $value;
    }

    /**
     * Fills the picker of the saved link type (a product shows its default SKU).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withPickers(array $data): array
    {
        $type = BannerLinkType::tryFrom((string) ($data[Banner::COLUMN_LINK_TYPE] ?? ''));
        $linkId = $data[Banner::COLUMN_LINK_ID] ?? null;

        if ($type !== null && isset(self::PICKERS[$type->value]) && $linkId !== null) {
            $data[self::PICKERS[$type->value]] = $type === BannerLinkType::Product ? app(AdminOptions::class)->defaultSkuOf((int) $linkId) : (int) $linkId;
        }

        return $data;
    }

    private static function linkType(Get $get): ?BannerLinkType
    {
        $value = $get(Banner::COLUMN_LINK_TYPE);

        return $value instanceof BannerLinkType ? $value : BannerLinkType::tryFrom((string) $value);
    }
}
