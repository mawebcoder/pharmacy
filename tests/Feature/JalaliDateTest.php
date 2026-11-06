<?php

declare(strict_types=1);

use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Support\Carbon;

it('stores times in UTC and displays them in Tehran time', function (): void {
    expect(config('app.timezone'))->toBe('UTC')
        ->and(now()->timezoneName)->toBe('UTC')
        ->and(config('app.display_timezone'))->toBe('Asia/Tehran')
        ->and(FilamentTimezone::get())->toBe('Asia/Tehran');
});

it('shows UTC timestamps as Jalali dates in Tehran time', function (): void {
    $storedAt = Carbon::parse('2026-11-06 10:30', 'UTC');

    expect($storedAt->toJalali())->toBe('1405/08/15')
        ->and($storedAt->toJalali('Y/m/d H:i'))->toBe('1405/08/15 14:00');
});

it('moves to the next Jalali day after midnight in Tehran even while it is still the same day in UTC', function (): void {
    expect(Carbon::parse('2026-11-06 21:00', 'UTC')->toJalali('Y/m/d H:i'))->toBe('1405/08/16 00:30');
});

it('starts the Jalali year on Nowruz', function (): void {
    expect(Carbon::parse('2026-03-21 12:00', 'UTC')->toJalali())->toBe('1405/01/01')
        ->and(Carbon::parse('2026-03-20 12:00', 'UTC')->toJalali())->toBe('1404/12/29');
});

it('binds a Gregorian value to the date picker with Jalali limits', function (): void {
    $html = (string) $this->blade(
        '<x-jalali-date label="تاریخ تحویل" name="delivery_date" value="2026-11-11" :min="$min" max="2026-12-06" />',
        ['min' => Carbon::parse('2026-11-06', 'UTC')],
    );

    expect($html)
        ->toContain('x-data="jalaliDate(')
        ->toContain('2026-11-11')
        ->toContain('2026-11-06')
        ->toContain('2026-12-06')
        ->toContain('name="delivery_date"')
        ->toContain('<label for="jalali-date-delivery-date"');
});

it('uses the Tehran calendar day as "today" in the date picker', function (): void {
    $this->travelTo(Carbon::parse('2026-11-06 21:00', 'UTC'));

    expect((string) $this->blade('<x-jalali-date name="delivery_date" />'))->toContain('2026-11-07');
});

it('shows validation errors under the date picker', function (): void {
    $this->withViewErrors(['delivery_date' => 'تاریخ تحویل الزامی است.']);

    $this->blade('<x-jalali-date name="delivery_date" hint="راهنما" />')
        ->assertSee('تاریخ تحویل الزامی است.')
        ->assertDontSee('راهنما');
});
