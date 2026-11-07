<?php

declare(strict_types=1);

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Carbon;

it('shows UTC timestamps in admin tables as Jalali dates in Tehran time', function (): void {
    $storedAt = Carbon::parse('2026-11-07 06:04:18', 'UTC');

    $column = TextColumn::make('created_at')->jalaliDateTime(); // @phpstan-ignore method.notFound

    expect($column->formatState($storedAt))->toBe('1405/08/16 09:34:18');
});

it('shows UTC timestamps on admin detail pages in Tehran time', function (): void {
    $entry = TextEntry::make('created_at')->jalaliDateTime()->container(Schema::make()); // @phpstan-ignore method.notFound

    expect($entry->formatState(Carbon::parse('2026-11-06 21:00:00', 'UTC')))->toBe('1405/08/16 00:30:00');
});
