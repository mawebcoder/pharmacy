<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/*
| Maintenance (owner, 2026-11-28): `php artisan down` shows the 503 page to customers and admins alike, without
| touching the database, saying the site is back in a few minutes (never an exact time).
*/
beforeEach(function (): void {
    config(['app.maintenance.driver' => 'array']);
    app()->maintenanceMode()->activate(['retry' => 300, 'status' => 503]);
});

it('shows the maintenance page without a database query, back in a few minutes', function (): void {
    DB::enableQueryLog();

    $this->get('/')
        ->assertStatus(503)
        ->assertSee('در حال به‌روزرسانی هستیم')
        ->assertSee('تا چند دقیقه دیگر برمی‌گردیم')
        ->assertDontSee('۵ دقیقه')
        ->assertSee('<meta http-equiv="refresh" content="60">', false);

    expect(DB::getQueryLog())->toBe([]);
});

it('shows the same page in the admin panel', function (): void {
    $this->get('/admin')->assertStatus(503)->assertSee('در حال به‌روزرسانی هستیم');
});
