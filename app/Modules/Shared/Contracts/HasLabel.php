<?php

declare(strict_types=1);

namespace App\Modules\Shared\Contracts;

/**
 * An enum whose cases have a Persian label for the UI (roadmap C4).
 */
interface HasLabel
{
    public function label(): string;
}
