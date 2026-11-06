<?php

declare(strict_types=1);

use App\Modules\Identity\Models\AdminUser;
use App\Modules\System\Jobs\WriteActivityLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;

it('captures a change into a queued job payload instead of writing during the request', function (): void {
    Queue::fake();
    $this->travelTo(Carbon::parse('2026-11-06 10:00', 'UTC'));
    $actor = AdminUser::factory()->create();
    $admin = AdminUser::factory()->create(['is_active' => true]);
    $this->actingAs($actor, 'admin');

    $admin->update(['is_active' => false]);

    expect(Activity::query()->where('event', 'updated')->exists())->toBeFalse();
    Queue::assertPushed(WriteActivityLog::class, fn (WriteActivityLog $job): bool => $job->payload['event'] === 'updated'
        && $job->payload['subject_id'] === $admin->id
        && $job->payload['causer_id'] === $actor->id
        && json_decode($job->payload['attribute_changes'], true) === ['attributes' => ['is_active' => false], 'old' => ['is_active' => true]]
        && $job->payload['created_at'] === '2026-11-06 10:00:00');
});

it('writes the captured change with the time it happened, even when the job runs later', function (): void {
    Queue::fake();
    $this->travelTo(Carbon::parse('2026-11-06 10:00', 'UTC'));
    $admin = AdminUser::factory()->create(['name' => 'سارا']);
    $admin->update(['name' => 'سارا محمدی']);
    $job = Queue::pushed(WriteActivityLog::class)->last();

    $this->travelTo(Carbon::parse('2026-11-06 12:30', 'UTC'));
    $job->handle();

    $activity = Activity::query()->where('event', 'updated')->sole();
    expect($activity->created_at->toDateTimeString())->toBe('2026-11-06 10:00:00')
        ->and($activity->subject->is($admin))->toBeTrue()
        ->and($activity->attribute_changes->toArray())->toEqual(['attributes' => ['name' => 'سارا محمدی'], 'old' => ['name' => 'سارا']]);
});

it('does not log a change whose transaction is rolled back', function (): void {
    $admin = AdminUser::factory()->create(['is_active' => true]);

    try {
        DB::transaction(function () use ($admin): void {
            $admin->update(['is_active' => false]);

            throw new RuntimeException('rolled back');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(Activity::query()->where('event', 'updated')->whereMorphedTo('subject', $admin)->exists())->toBeFalse();
});
