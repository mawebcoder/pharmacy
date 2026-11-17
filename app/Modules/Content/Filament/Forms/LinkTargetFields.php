<?php

declare(strict_types=1);

namespace App\Modules\Content\Filament\Forms;

use App\Modules\Catalog\Filament\Forms\ImageSelect;
use App\Modules\Catalog\Queries\AdminOptions;
use App\Modules\Content\Enums\LinkType;
use App\Modules\Content\Queries\PageLinks;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Where a banner or menu item leads (roadmap S13b, S34): the target type, a picker per item type and the https
 * address. Each item type has its own picker field; toLinkId() turns them into link_id and withPickers() fills them
 * back. The record's columns are link_type, link_id and external_url.
 */
final class LinkTargetFields
{
    public const string TYPE = 'link_type';

    public const string LINK_ID = 'link_id';

    public const string EXTERNAL_URL = 'external_url';

    /**
     * Picker field per item link type (products are picked by SKU).
     */
    public const array PICKERS = [
        'category' => 'link_category_id',
        'collection' => 'link_collection_id',
        'brand' => 'link_brand_id',
        'tag' => 'link_tag_id',
        'product' => 'link_sku_id',
        'page' => 'link_page_id',
    ];

    /**
     * @param  list<LinkType>|null  $types  the targets offered (all by default)
     * @return list<Field>
     */
    public static function make(?array $types = null): array
    {
        $options = app(AdminOptions::class);
        $pickers = [];

        foreach (self::PICKERS as $type => $field) {
            if ($type === LinkType::Page->value) {
                $pickers[] = Select::make($field)
                    ->label('صفحه')
                    ->options(fn (): array => app(PageLinks::class)->options())
                    ->searchable()
                    ->required()
                    ->helperText('صفحه پیش‌نویس تا وقتی منتشر نشود، پیوندش نمایش داده نمی‌شود.')
                    ->visible(fn (Get $get): bool => self::linkType($get) === LinkType::Page);

                continue;
            }

            $select = Select::make($field)
                ->label('مقصد')
                ->required()
                ->visible(fn (Get $get): bool => self::linkType($get) === LinkType::from($type));
            $pickers[] = $type === 'product' ? ImageSelect::skus($select) : ImageSelect::images($select, $type, fn (): array => $options->names($type));
        }

        $types ??= LinkType::cases();

        return [
            Select::make(self::TYPE)
                ->label('نوع مقصد')
                ->options(collect($types)->mapWithKeys(fn (LinkType $type): array => [$type->value => $type->label()])->all())
                ->required()
                ->live(),
            ...$pickers,
            TextInput::make(self::EXTERNAL_URL)
                ->label('آدرس بیرونی')
                ->url()
                ->startsWith(['https://'])
                ->extraInputAttributes(['dir' => 'ltr'])
                ->helperText('در برگه جدید باز می‌شود.')
                ->required()
                ->visible(fn (Get $get): bool => self::linkType($get) === LinkType::External),
        ];
    }

    /**
     * link_id from the picker of the chosen type (a picked SKU becomes its product).
     *
     * @param  array<string, mixed>  $data
     */
    public static function toLinkId(array $data): ?int
    {
        $type = LinkType::tryFrom((string) ($data[self::TYPE] ?? ''));
        $field = $type === null ? null : (self::PICKERS[$type->value] ?? null);
        $value = $field === null ? null : ($data[$field] ?? null);

        if (blank($value)) {
            return null;
        }

        return $type === LinkType::Product
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
        $type = $data[self::TYPE] ?? null;
        $type = $type instanceof LinkType ? $type : LinkType::tryFrom((string) $type);
        $linkId = $data[self::LINK_ID] ?? null;

        if ($type !== null && isset(self::PICKERS[$type->value]) && $linkId !== null) {
            $data[self::PICKERS[$type->value]] = $type === LinkType::Product ? app(AdminOptions::class)->defaultSkuOf((int) $linkId) : (int) $linkId;
        }

        return $data;
    }

    private static function linkType(Get $get): ?LinkType
    {
        $value = $get(self::TYPE);

        return $value instanceof LinkType ? $value : LinkType::tryFrom((string) $value);
    }
}
