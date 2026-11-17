<?php

declare(strict_types=1);

namespace App\Modules\Settings\Queries;

use App\Modules\Media\Contracts\StoredImage;
use App\Modules\Settings\Models\TrustBadge;
use App\Modules\Settings\Support\TrustBadgeEmbed;

/**
 * The footer's trust badges in the admin's order (roadmap S35): each is either cleaned embed HTML (e-Namad,
 * Samandehi) or an image with an optional link. A badge with neither is left out. Not cached: its images are made in
 * the background and the list is one small query.
 */
final readonly class TrustBadges
{
    /**
     * @return list<array{title: string, html: string|null, image: StoredImage|null, url: string|null}>
     */
    public function shown(): array
    {
        $badges = [];

        foreach (TrustBadge::query()->active()->with('media')->orderBy(TrustBadge::COLUMN_SORT)->orderBy(TrustBadge::COLUMN_ID)->get() as $badge) {
            $html = TrustBadgeEmbed::clean($badge->getEmbedCode());
            $image = $html === null ? $badge->getImage() : null;

            if ($html !== null || $image !== null) {
                $badges[] = ['title' => $badge->getTitle(), 'html' => $html, 'image' => $image, 'url' => $html === null ? $badge->getLinkUrl() : null];
            }
        }

        return $badges;
    }
}
