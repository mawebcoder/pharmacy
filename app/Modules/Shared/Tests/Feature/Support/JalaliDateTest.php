<?php

declare(strict_types=1);

use App\Modules\Shared\Support\JalaliDate;
use Illuminate\Support\Carbon;

it('displays a UTC timestamp as a Jalali date in Tehran time with Persian digits', function (): void {
    $storedAt = Carbon::parse('2026-11-06 10:30', 'UTC');

    expect(JalaliDate::display($storedAt))->toBe('۱۵ آبان ۱۴۰۵')
        ->and(JalaliDate::display($storedAt, 'Y/m/d H:i'))->toBe('۱۴۰۵/۰۸/۱۵ ۱۴:۰۰');
});

it('keeps Latin digits for data formatting', function (): void {
    expect(JalaliDate::format(Carbon::parse('2026-11-06 21:00', 'UTC'), 'Y/m/d H:i'))->toBe('1405/08/16 00:30');
});

it('displays nothing for a missing date', function (): void {
    expect(JalaliDate::display(null))->toBe('');
});

it('renders the Blade display helpers', function (): void {
    $html = (string) $this->blade('@toman($price) | @jalali($date)', [
        'price' => 12_500_000,
        'date' => Carbon::parse('2026-11-06 10:30', 'UTC'),
    ]);

    expect($html)->toBe('۱٬۲۵۰٬۰۰۰ تومان | ۱۵ آبان ۱۴۰۵');
});
