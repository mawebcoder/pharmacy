<?php

declare(strict_types=1);

namespace App\Modules\Content\Data;

use App\Modules\Content\Enums\HomeWidgetType;
use App\Modules\Content\Models\HomeWidget;
use Illuminate\Support\Carbon;

/**
 * Input for creating or updating a home widget. settings keeps only the keys of its type: collection_id, or the
 * ordered brand_ids / category_ids / tag_ids. Times are UTC.
 */
final readonly class HomeWidgetData
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public HomeWidgetType $type,
        public ?string $titleFa,
        public array $settings,
        public bool $isActive,
        public ?Carbon $startsAt,
        public ?Carbon $endsAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  keys are the home_widgets columns plus the settings keys at the top level
     */
    public static function fromArray(array $data): self
    {
        $type = $data[HomeWidget::COLUMN_TYPE] ?? HomeWidgetType::Trust;
        $type = $type instanceof HomeWidgetType ? $type : HomeWidgetType::from((string) $type);
        $title = trim((string) ($data[HomeWidget::COLUMN_TITLE_FA] ?? ''));
        $time = fn (string $key): ?Carbon => blank($data[$key] ?? null) ? null : Carbon::parse($data[$key])->utc();

        $settings = match (true) {
            $type === HomeWidgetType::Collection => ['collection_id' => blank($data['collection_id'] ?? null) ? null : (int) $data['collection_id']],
            $type->itemsKey() !== null => [(string) $type->itemsKey() => array_map(intval(...), array_values((array) ($data[$type->itemsKey()] ?? [])))],
            default => [],
        };

        return new self(
            type: $type,
            titleFa: $title === '' ? null : $title,
            settings: $settings,
            isActive: (bool) ($data[HomeWidget::COLUMN_IS_ACTIVE] ?? true),
            startsAt: $time(HomeWidget::COLUMN_STARTS_AT),
            endsAt: $time(HomeWidget::COLUMN_ENDS_AT),
        );
    }
}
