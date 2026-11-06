<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Modules\Shared\Support\Modules;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
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
            ->domain(config('app.admin_domain'))
            ->path('')
            ->login()
            ->spa()
            ->brandName(config('app.name'))
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
            ]);
    }
}
