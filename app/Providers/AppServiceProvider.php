<?php

declare(strict_types=1);

namespace App\Providers;

use Ariaieboy\Jalali\Jalali;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Facades\FilamentTimezone;
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
     * Timestamps are stored in UTC; display every date as Shamsi (Jalali) in the display timezone (Asia/Tehran),
     * on both the storefront and the admin panel.
     */
    protected function configureJalaliDates(): void
    {
        // Usage in Blade: {{ $order->created_at->toJalali('Y/m/d H:i') }}
        Carbon::macro('toJalali', function (string $format = 'Y/m/d'): string {
            /** @var Carbon $this */
            return Jalali::fromCarbon($this->copy()->setTimezone(config('app.display_timezone')))->format($format);
        });

        FilamentTimezone::set(config('app.display_timezone'));

        // jalali() is a macro registered by ariaieboy/filament-jalali, which PHPStan cannot see.
        DatePicker::configureUsing(fn (DatePicker $picker) => $picker->jalali()); // @phpstan-ignore method.notFound
        DateTimePicker::configureUsing(fn (DateTimePicker $picker) => $picker->jalali()); // @phpstan-ignore method.notFound
    }

    /**
     * Prices are whole rials, always shown grouped in threes: Number::rial(4850000) → "۴٬۸۵۰٬۰۰۰ ریال".
     */
    protected function configurePrices(): void
    {
        Number::macro('rial', function (int|float|string|null $amount, bool $withUnit = true): string {
            $formatted = (string) Number::format((int) round((float) $amount), locale: 'fa');

            return $withUnit ? "{$formatted} ریال" : $formatted;
        });

        TextColumn::macro('rial', function (): TextColumn {
            // Macros run bound to the component; PHPStan only sees the provider as $this.
            /** @var TextColumn $this */ // @phpstan-ignore varTag.nativeType
            return $this->formatStateUsing(fn ($state): string => Number::rial($state))->alignEnd();
        });

        TextEntry::macro('rial', function (): TextEntry {
            // Macros run bound to the component; PHPStan only sees the provider as $this.
            /** @var TextEntry $this */ // @phpstan-ignore varTag.nativeType
            return $this->formatStateUsing(fn ($state): string => Number::rial($state));
        });

        // Groups digits while typing (4,850,000) and stores the plain integer.
        TextInput::macro('rial', function (): TextInput {
            // Macros run bound to the component; PHPStan only sees the provider as $this.
            /** @var TextInput $this */ // @phpstan-ignore varTag.nativeType
            return $this
                ->mask(RawJs::make("\$money(\$input, '.', ',', 0)"))
                ->stripCharacters(',')
                ->numeric()
                ->minValue(0)
                ->suffix('ریال');
        });
    }
}
