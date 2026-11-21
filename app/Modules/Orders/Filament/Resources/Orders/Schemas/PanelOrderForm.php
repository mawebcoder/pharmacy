<?php

declare(strict_types=1);

namespace App\Modules\Orders\Filament\Resources\Orders\Schemas;

use App\Modules\Catalog\Filament\Forms\ImageSelect;
use App\Modules\Identity\Queries\CustomerAddresses;
use App\Modules\Identity\Queries\CustomerLabels;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * «ثبت سفارش» in the panel (owner, 2026-11-19): an existing customer and one of their saved addresses, SKUs and
 * quantities at the site's prices, an optional discount with its reason, and a note. Shipping is priced as checkout
 * prices it; the payment link is texted to the customer.
 */
final class PanelOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('مشتری و آدرس')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('customer_id')
                        ->label('مشتری')
                        ->required()
                        ->searchable()
                        ->live()
                        ->getSearchResultsUsing(fn (string $search): array => app(CustomerLabels::class)->search($search))
                        ->getOptionLabelUsing(fn (mixed $value): ?string => is_numeric($value) ? (app(CustomerLabels::class)->for([(int) $value])[(int) $value] ?? null) : null)
                        ->helperText('با موبایل یا نام جستجو کنید؛ فقط مشتری‌هایی که در سایت ثبت‌نام کرده‌اند.')
                        ->afterStateUpdated(fn (Set $set) => $set('address_id', null)),
                    Select::make('address_id')
                        ->label('آدرس ارسال')
                        ->required()
                        ->options(fn (Get $get): array => is_numeric($get('customer_id')) ? app(CustomerAddresses::class)->options((int) $get('customer_id')) : [])
                        ->helperText('از آدرس‌های ذخیره‌شده مشتری.'),
                ]),
            Section::make('کالاها')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('items')
                        ->hiddenLabel()
                        ->addActionLabel('افزودن کالا')
                        ->reorderable(false)
                        ->minItems(1)
                        ->columns(['default' => 1, 'md' => 4])
                        ->schema([
                            ImageSelect::skus(Select::make('variant_id')->label('کالا (SKU)')->required()->distinct())->columnSpan(['md' => 3]),
                            TextInput::make('quantity')->label('تعداد')->numeric()->integer()->required()->minValue(1)->maxValue(100)->default(1),
                        ]),
                ]),
            Section::make('تخفیف و توضیح')
                ->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('manual_discount')
                        ->label('تخفیف')
                        ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                        ->default(0)
                        ->minValue(0)
                        ->live(onBlur: true)
                        ->helperText('روی جمع کالاها؛ قیمت‌ها و هزینه ارسال همان قیمت سایت است.'),
                    Textarea::make('manual_discount_reason')
                        ->label('دلیل تخفیف')
                        ->rows(2)
                        ->maxLength(500)
                        ->required(fn (Get $get): bool => (int) $get('manual_discount') > 0),
                    Textarea::make('customer_note')->label('توضیح سفارش')->rows(2)->maxLength(1000)->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * «ویرایش سفارش» of an unpaid panel order (owner, 2026-11-19): the same address, items and discount fields, for the
     * order's own customer; a paid order is edited without the discount.
     *
     * @return list<Component>
     */
    public static function editFields(int $customerId, bool $withDiscount = true): array
    {
        return array_values(array_filter([
            Select::make('address_id')
                ->label('آدرس ارسال')
                ->required()
                ->options(app(CustomerAddresses::class)->options($customerId)),
            Repeater::make('items')
                ->label('کالاها')
                ->addActionLabel('افزودن کالا')
                ->reorderable(false)
                ->minItems(1)
                ->columns(['default' => 1, 'md' => 4])
                ->schema([
                    ImageSelect::skus(Select::make('variant_id')->label('کالا (SKU)')->required()->distinct())->columnSpan(['md' => 3]),
                    TextInput::make('quantity')->label('تعداد')->numeric()->integer()->required()->minValue(1)->maxValue(100),
                ]),
            $withDiscount ? TextInput::make('manual_discount')
                ->label('تخفیف')
                ->toman() // @phpstan-ignore method.notFound (macro, AdminPanelProvider)
                ->minValue(0) : null,
            $withDiscount ? Textarea::make('manual_discount_reason')
                ->label('دلیل تخفیف')
                ->rows(2)
                ->maxLength(500)
                ->required(fn (Get $get): bool => (int) $get('manual_discount') > 0) : null,
        ]));
    }
}
