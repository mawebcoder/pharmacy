<?php

declare(strict_types=1);

namespace App\Modules\Orders\Enums;

/**
 * Where an order stands against one checkpoint of its tracker (owner, 2026-11-16): passed (ticked), the one it is in
 * now, or still to come (a waiting clock).
 */
enum CheckpointState: string
{
    case Passed = 'passed';
    case Current = 'current';
    case Upcoming = 'upcoming';
}
