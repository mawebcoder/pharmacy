<?php

namespace App\Providers;

use Ariaieboy\Jalali\Jalali;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\RawJs;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureJalaliDates();
        $this->configurePrices();
    }

    /**
     * Display every date as Shamsi (Jalali), on both the storefront and the admin panel.
     */
    protected function configureJalaliDates(): void
    {
        // Usage in Blade: {{ $order->created_at->toJalali('Y/m/d H:i') }}
        Carbon::macro('toJalali', function (string $format = 'Y/m/d'): string {
            /** @var Carbon $this */
            return Jalali::fromCarbon($this)->format($format);
        });

        DatePicker::configureUsing(fn (DatePicker $picker) => $picker->jalali());
        DateTimePicker::configureUsing(fn (DateTimePicker $picker) => $picker->jalali());
    }

    /**
     * Prices are whole rials, always shown grouped in threes: Number::rial(4850000) → "۴٬۸۵۰٬۰۰۰ ریال".
     */
    protected function configurePrices(): void
    {
        Number::macro('rial', function (int|float|string|null $amount, bool $withUnit = true): string {
            $formatted = Number::format((int) round((float) $amount), locale: 'fa');

            return $withUnit ? "{$formatted} ریال" : $formatted;
        });

        TextColumn::macro('rial', function (): TextColumn {
            /** @var TextColumn $this */
            return $this->formatStateUsing(fn ($state): string => Number::rial($state))->alignEnd();
        });

        TextEntry::macro('rial', function (): TextEntry {
            /** @var TextEntry $this */
            return $this->formatStateUsing(fn ($state): string => Number::rial($state));
        });

        // Groups digits while typing (4,850,000) and stores the plain integer.
        TextInput::macro('rial', function (): TextInput {
            /** @var TextInput $this */
            return $this
                ->mask(RawJs::make("\$money(\$input, '.', ',', 0)"))
                ->stripCharacters(',')
                ->numeric()
                ->minValue(0)
                ->suffix('ریال');
        });
    }
}
