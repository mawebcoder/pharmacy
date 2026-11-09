<?php

declare(strict_types=1);

namespace App\Modules\Content\Data;

use App\Modules\Content\Enums\BannerLinkType;
use App\Modules\Content\Models\Banner;
use Illuminate\Support\Carbon;

/**
 * Input for creating or updating a banner (images are uploaded separately). Times are UTC.
 */
final readonly class BannerData
{
    public function __construct(
        public string $title,
        public BannerLinkType $linkType,
        public ?int $linkId,
        public ?string $externalUrl,
        public bool $isActive,
        public ?Carbon $startsAt,
        public ?Carbon $endsAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  keys are the banners columns (Banner::COLUMN_*)
     */
    public static function fromArray(array $data): self
    {
        $type = $data[Banner::COLUMN_LINK_TYPE] ?? BannerLinkType::External;
        $type = $type instanceof BannerLinkType ? $type : BannerLinkType::from((string) $type);
        $url = trim((string) ($data[Banner::COLUMN_EXTERNAL_URL] ?? ''));
        $time = fn (string $key): ?Carbon => blank($data[$key] ?? null) ? null : Carbon::parse($data[$key])->utc();

        return new self(
            title: trim((string) ($data[Banner::COLUMN_TITLE] ?? '')),
            linkType: $type,
            linkId: $type->needsItem() && filled($data[Banner::COLUMN_LINK_ID] ?? null) ? (int) $data[Banner::COLUMN_LINK_ID] : null,
            externalUrl: $type === BannerLinkType::External && $url !== '' ? $url : null,
            isActive: (bool) ($data[Banner::COLUMN_IS_ACTIVE] ?? true),
            startsAt: $time(Banner::COLUMN_STARTS_AT),
            endsAt: $time(Banner::COLUMN_ENDS_AT),
        );
    }
}
