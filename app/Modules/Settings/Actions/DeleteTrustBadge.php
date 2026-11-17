<?php

declare(strict_types=1);

namespace App\Modules\Settings\Actions;

use App\Modules\Settings\Models\TrustBadge;

/**
 * Removes a trust badge and its image (roadmap S35).
 */
final class DeleteTrustBadge
{
    public function __invoke(TrustBadge $badge): void
    {
        $badge->delete();
    }
}
