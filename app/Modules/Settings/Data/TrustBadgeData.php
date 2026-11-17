<?php

declare(strict_types=1);

namespace App\Modules\Settings\Data;

use App\Modules\Settings\Models\TrustBadge;
use App\Modules\Settings\Support\TrustBadgeEmbed;

/**
 * Input for a trust badge: the embed code is cleaned (TrustBadgeEmbed) and kept as typed when something survives;
 * empty texts are null.
 */
final readonly class TrustBadgeData
{
    public function __construct(
        public string $title,
        public ?string $embedCode,
        public bool $embedCodeTyped,
        public ?string $linkUrl,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data  keys are the trust_badges columns (TrustBadge::COLUMN_*)
     */
    public static function fromArray(array $data): self
    {
        $embed = trim((string) ($data[TrustBadge::COLUMN_EMBED_CODE] ?? ''));
        $url = trim((string) ($data[TrustBadge::COLUMN_LINK_URL] ?? ''));

        return new self(
            title: trim(strip_tags((string) ($data[TrustBadge::COLUMN_TITLE] ?? ''))),
            embedCode: TrustBadgeEmbed::clean($embed),
            embedCodeTyped: $embed !== '',
            linkUrl: $url === '' ? null : $url,
            isActive: (bool) ($data[TrustBadge::COLUMN_IS_ACTIVE] ?? true),
        );
    }
}
