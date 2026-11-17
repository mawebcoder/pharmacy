<?php

declare(strict_types=1);

namespace App\Modules\Settings\Actions;

use App\Modules\Settings\Data\TrustBadgeData;
use App\Modules\Settings\Exceptions\TrustBadgeRefused;
use App\Modules\Settings\Models\TrustBadge;

/**
 * Adds a trust badge (at the end) or changes one (roadmap S35). An embed code with nothing from e-Namad or Samandehi
 * left after cleaning is refused, so a pasted script never becomes an empty badge. A badge with an embed code keeps
 * no link of its own: the code carries it.
 */
final class SaveTrustBadge
{
    /**
     * @throws TrustBadgeRefused
     */
    #[\NoDiscard]
    public function __invoke(TrustBadgeData $data, ?TrustBadge $badge = null): TrustBadge
    {
        if ($data->embedCodeTyped && $data->embedCode === null) {
            throw TrustBadgeRefused::embedNotAccepted();
        }

        if (! $badge instanceof TrustBadge) {
            $badge = (new TrustBadge)->setSort((int) TrustBadge::query()->max(TrustBadge::COLUMN_SORT) + 1);
        }

        $badge->setTitle($data->title)
            ->setEmbedCode($data->embedCode)
            ->setLinkUrl($data->embedCode === null ? $data->linkUrl : null)
            ->setIsActive($data->isActive)
            ->save();

        return $badge;
    }
}
