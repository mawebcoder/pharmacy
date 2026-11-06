<?php

declare(strict_types=1);

namespace App\Modules\System;

use App\Modules\Shared\ModuleServiceProvider;
use App\Modules\System\Policies\ActivityPolicy;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

final class SystemServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Gate::policy(Activity::class, ActivityPolicy::class);
    }
}
