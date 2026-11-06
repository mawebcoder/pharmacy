<?php

declare(strict_types=1);

namespace App\Modules\Shared\Contracts;

/**
 * A model that names itself in the activity log, so the System module can show it without depending on
 * another module's models (roadmap C3 rule 1).
 */
interface HasActivityLogLabel
{
    public function activityLogLabel(): string;
}
