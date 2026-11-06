<?php

declare(strict_types=1);

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;

it('shows rial values in admin table columns as toman', function (): void {
    expect(TextColumn::make('price')->toman()->formatState(12_500_000))->toBe('۱٬۲۵۰٬۰۰۰ تومان');
});

it('shows rial values in admin detail entries as toman', function (): void {
    $entry = TextEntry::make('price')->toman()->container(Schema::make());

    expect($entry->formatState(12_500_000))->toBe('۱٬۲۵۰٬۰۰۰ تومان');
});
