<?php

declare(strict_types=1);

namespace App\Modules\Cart\Filament\Resources\Coupons;

use App\Modules\Cart\Enums\CouponType;
use App\Modules\Cart\Http\Requests\SaveCouponRequest;
use App\Modules\Cart\Models\Coupon;
use App\Modules\Catalog\Filament\Forms\ImageSelect;
use App\Modules\Catalog\Queries\AdminOptions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * The coupon form (roadmap D3.4, S22). A percent coupon has a value 1–100 and an optional cap; a fixed one an amount
 * in toman; free shipping neither. «محدود به» lists are optional: left empty, the coupon covers the whole cart.
 * Products are picked by SKU (as elsewhere in the admin); what is saved is the product, with all its SKUs.
 */
final class CouponForm
{
    public const string FIXED_VALUE = 'fixed_value';

    public const string SKU_IDS = 'sku_ids';

    public static function configure(Schema $schema): Schema
    {
        $options = app(AdminOptions::class);

        return $schema
            ->columns(1)
            ->components([
                Section::make('کد و تخفیف')
                    ->columns(2)
                    ->schema([
                        TextInput::make(Coupon::COLUMN_CODE)
                            ->label('کد')
                            ->required()
                            ->maxLength(32)
                            ->extraInputAttributes(['dir' => 'ltr', 'style' => 'text-transform: uppercase'])
                            ->helperText('حروف انگلیسی، عدد و «-»؛ بزرگ و کوچک یکی است.')
                            ->dehydrateStateUsing(fn (?string $state): string => Coupon::normalizeCode((string) $state))
                            ->mutateStateForValidationUsing(fn (?string $state): string => Coupon::normalizeCode((string) $state))
                            ->rules(fn (?Model $record): array => SaveCouponRequest::baseRules($record instanceof Coupon ? $record->getId() : null)[Coupon::COLUMN_CODE])
                            ->validationMessages(['regex' => 'کد فقط حروف انگلیسی، عدد و «-» دارد.', 'unique' => 'این کد برای کوپن دیگری است.']),
                        Select::make(Coupon::COLUMN_TYPE)
                            ->label('نوع تخفیف')
                            ->options(CouponType::options())
                            ->default(CouponType::Percent->value)
                            ->required()
                            ->live(),
                        TextInput::make(Coupon::COLUMN_VALUE)
                            ->label('درصد تخفیف')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(100)
                            ->suffix('٪')
                            ->required()
                            ->visible(fn (Get $get): bool => self::type($get) === CouponType::Percent),
                        TextInput::make(Coupon::COLUMN_MAX_DISCOUNT)
                            ->label('حداکثر تخفیف')
                            ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                            ->helperText('خالی یعنی بدون سقف.')
                            ->visible(fn (Get $get): bool => self::type($get) === CouponType::Percent),
                        TextInput::make(self::FIXED_VALUE)
                            ->label('مبلغ تخفیف')
                            ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                            ->required()
                            ->helperText('هیچ‌وقت بیشتر از جمع کالاهای مشمول نمی‌شود.')
                            ->visible(fn (Get $get): bool => self::type($get) === CouponType::Fixed),
                        Toggle::make(Coupon::COLUMN_IS_ACTIVE)->label('فعال')->default(true)->inline(false),
                    ]),
                Section::make('شرایط')
                    ->columns(2)
                    ->schema([
                        TextInput::make(Coupon::COLUMN_MIN_ORDER_AMOUNT)
                            ->label('حداقل خرید')
                            ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                            ->helperText('فقط کالاهای مشمول کد حساب می‌شوند؛ خالی یعنی بدون حداقل.'),
                        Toggle::make(Coupon::COLUMN_APPLIES_TO_SALE_ITEMS)
                            ->label('شامل کالاهای حراج')
                            ->helperText('خاموش: کالاهایی که قیمت حراج دارند تخفیف نمی‌گیرند.')
                            ->inline(false),
                        TextInput::make(Coupon::COLUMN_USAGE_LIMIT_TOTAL)
                            ->label('سقف کل استفاده')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->helperText('خالی یعنی بدون سقف.'),
                        TextInput::make(Coupon::COLUMN_USAGE_LIMIT_PER_CUSTOMER)
                            ->label('سقف استفاده هر مشتری')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->default(1)
                            ->helperText('خالی یعنی بدون سقف.'),
                        DateTimePicker::make(Coupon::COLUMN_STARTS_AT)->label('شروع')->helperText('خالی یعنی از همین حالا.'),
                        DateTimePicker::make(Coupon::COLUMN_ENDS_AT)->label('پایان')->helperText('خالی یعنی بدون پایان.')->after(Coupon::COLUMN_STARTS_AT),
                        Toggle::make(Coupon::COLUMN_FIRST_ORDER_ONLY)->label('فقط اولین خرید')->inline(false),
                    ]),
                Section::make('محدود به')
                    ->description('اختیاری. خالی بماند تا کد روی همه کالاهای سبد اعمال شود. اگر پر شود، کالایی مشمول است که در یکی از دسته‌ها (با زیردسته‌ها)، یکی از برندها یا جزو محصولات انتخاب‌شده باشد.')
                    ->columns(1)
                    ->collapsible()
                    ->schema([
                        ImageSelect::images(Select::make('category_ids')->label('دسته‌بندی‌ها')->multiple(), 'category', fn (): array => $options->names('category')),
                        ImageSelect::images(Select::make('brand_ids')->label('برندها')->multiple(), 'brand', fn (): array => $options->names('brand')),
                        ImageSelect::skus(Select::make(self::SKU_IDS)->label('محصولات')->multiple()),
                    ]),
            ]);
    }

    /**
     * Form state → CouponData input: the fixed amount into «value», picked SKUs into their products.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function toData(array $data): array
    {
        if (($data[Coupon::COLUMN_TYPE] ?? null) === CouponType::Fixed->value || ($data[Coupon::COLUMN_TYPE] ?? null) === CouponType::Fixed) {
            $data[Coupon::COLUMN_VALUE] = $data[self::FIXED_VALUE] ?? 0;
        }

        $data['product_ids'] = app(AdminOptions::class)->productIdsOfSkus(array_values(array_map(intval(...), (array) ($data[self::SKU_IDS] ?? []))));

        return $data;
    }

    /**
     * Record → form state: the fixed amount, the include lists and each product's default SKU.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fill(Coupon $coupon, array $data): array
    {
        if ($coupon->getType() === CouponType::Fixed) {
            $data[self::FIXED_VALUE] = $coupon->getValue();
        }

        $options = app(AdminOptions::class);
        $data['category_ids'] = $coupon->includedIds('coupon_categories');
        $data['brand_ids'] = $coupon->includedIds('coupon_brands');
        $data[self::SKU_IDS] = array_values(array_filter(array_map(
            $options->defaultSkuOf(...),
            $coupon->includedIds('coupon_products'),
        )));

        return $data;
    }

    private static function type(Get $get): ?CouponType
    {
        $value = $get(Coupon::COLUMN_TYPE);

        return $value instanceof CouponType ? $value : CouponType::tryFrom((string) $value);
    }
}
