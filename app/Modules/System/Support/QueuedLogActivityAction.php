<?php

declare(strict_types=1);

namespace App\Modules\System\Support;

use App\Modules\System\Jobs\WriteActivityLog;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Actions\LogActivityAction;

/**
 * Audit logging off the request path: the change is captured now (who, what, old/new values, when) and the
 * activity row is written by a queued job from that payload, after the surrounding transaction commits.
 */
final class QueuedLogActivityAction extends LogActivityAction
{
    protected function save(Model $activity): void
    {
        $now = $activity->freshTimestamp();

        $activity->setCreatedAt($activity->getAttribute('created_at') ?? $now);
        $activity->setUpdatedAt($now);

        WriteActivityLog::dispatch($activity->getAttributes());
    }
}
