<?php

declare(strict_types=1);

use App\Modules\Catalog\Jobs\MarkNearExpiry;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Console\Scheduling\Schedule;

it('flags variants that came within 90 days of expiry and clears corrected ones', function (): void {
    $soon = ProductVariant::factory()->expiringOn(now()->addDays(60)->toDateString())->create();
    $later = ProductVariant::factory()->expiringOn(now()->addDays(120)->toDateString())->create([ProductVariant::COLUMN_IS_NEAR_EXPIRY => true]);
    $none = ProductVariant::factory()->create();

    (new MarkNearExpiry)->handle();

    expect($soon->fresh()->isNearExpiry())->toBeTrue()
        ->and($later->fresh()->isNearExpiry())->toBeFalse()
        ->and($none->fresh()->isNearExpiry())->toBeFalse();
});

it('runs every night at 02:00 Tehran time', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->description, MarkNearExpiry::class));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 2 * * *')
        ->and($event->timezone)->toBe('Asia/Tehran');
});
