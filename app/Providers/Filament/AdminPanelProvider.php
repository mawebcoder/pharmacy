<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Modules\Identity\Http\Middleware\LogOutIdleAdmins;
use App\Modules\Shared\Support\Modules;
use App\Modules\Shared\Support\Toman;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Infolists\Components\TextEntry;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Admin display rules (roadmap B12): Jalali dates in Tehran time, money in toman (stored as rial).
     */
    public function boot(): void
    {
        FilamentTimezone::set((string) config('app.display_timezone'));

        // jalali() is a macro registered by ariaieboy/filament-jalali, which PHPStan cannot see.
        DatePicker::configureUsing(fn (DatePicker $picker) => $picker->jalali()); // @phpstan-ignore method.notFound
        DateTimePicker::configureUsing(fn (DateTimePicker $picker) => $picker->jalali()); // @phpstan-ignore method.notFound

        $this->registerTomanMacros();
    }

    /**
     * ->toman() on table columns, infolist entries and inputs. The database value is always rial.
     */
    private function registerTomanMacros(): void
    {
        TextColumn::macro('toman', function (): TextColumn {
            // Macros run bound to the component; PHPStan only sees the provider as $this.
            /** @var TextColumn $this */ // @phpstan-ignore varTag.nativeType
            return $this->formatStateUsing(fn (int|float|string|null $state): string => Toman::format($state))->alignEnd();
        });

        TextEntry::macro('toman', function (): TextEntry {
            // Macros run bound to the component; PHPStan only sees the provider as $this.
            /** @var TextEntry $this */ // @phpstan-ignore varTag.nativeType
            return $this->formatStateUsing(fn (int|float|string|null $state): string => Toman::format($state));
        });

        // Admins type toman with thousands separators (485,000); the rial value (4850000) is saved.
        TextInput::macro('toman', function (): TextInput {
            // Macros run bound to the component; PHPStan only sees the provider as $this.
            /** @var TextInput $this */ // @phpstan-ignore varTag.nativeType
            return $this
                ->mask(RawJs::make("\$money(\$input, '.', ',', 0)"))
                ->stripCharacters([',', '٬'])
                ->numeric()
                ->minValue(0)
                ->suffix(Toman::UNIT)
                ->formatStateUsing(fn (int|float|string|null $state): ?int => blank($state) ? null : Toman::fromRial($state))
                ->dehydrateStateUsing(fn (int|float|string|null $state): ?int => Toman::rialFromInput($state));
        });
    }

    public function panel(Panel $panel): Panel
    {
        $panel = $this->configurePanel($panel);

        // Each module registers its own admin resources, pages and widgets (roadmap C3 rule 3).
        foreach (Modules::names() as $module) {
            $panel
                ->discoverResources(in: app_path("Modules/{$module}/Filament/Resources"), for: "App\\Modules\\{$module}\\Filament\\Resources")
                ->discoverPages(in: app_path("Modules/{$module}/Filament/Pages"), for: "App\\Modules\\{$module}\\Filament\\Pages")
                ->discoverWidgets(in: app_path("Modules/{$module}/Filament/Widgets"), for: "App\\Modules\\{$module}\\Filament\\Widgets");
        }

        return $panel;
    }

    private function configurePanel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            // Staff only, with a mandatory authenticator app (TOTP) and recovery codes (roadmap D16).
            ->authGuard('admin')
            ->login()
            ->profile()
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ], isRequired: true)
            ->spa()
            ->brandName((string) config('app.display_name'))
            ->font('Vazirmatn')
            ->colors([
                'primary' => Color::hex('#0F766E'),
                'danger' => Color::Red,
                'warning' => Color::hex('#F59E0B'),
                'success' => Color::hex('#16A34A'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            // Sidebar groups in roadmap B6 order; a group appears once it has a page.
            ->navigationGroups(['کاتالوگ', 'قیمت‌گذاری', 'فروش', 'ارسال', 'محتوا', 'مشتریان', 'تنظیمات', 'سیستم'])
            ->navigationItems([
                NavigationItem::make('صف‌ها (Horizon)')
                    ->url('/horizon', shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedQueueList)
                    ->group('سیستم'),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                LogOutIdleAdmins::class,
            ], isPersistent: true);
    }
}
