<?php

declare(strict_types=1);

namespace App\Providers;

use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\ServiceProvider;
use TallStackUi\Facades\TallStackUi;

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
        $this->customizeTallStackUi();

        // Filament imports belong to admins (admin_users), not App\Models\User: the catalog import (owner, 2026-11-27).
        Import::polymorphicUserRelationship();
    }

    /**
     * Storefront UI kit adjustments (roadmap B10). Colours come from the B3 tokens in resources/css/app.css.
     */
    protected function customizeTallStackUi(): void
    {
        // The dialog's cancel action is a neutral secondary button, not a red one (one strong CTA per view).
        TallStackUi::customize()
            ->dialog()
            ->block('buttons.cancel.base', 'w-full text-sm focus:ring-0! focus:ring-offset-0! bg-card! text-ink-900! ring-1 ring-line-200! hover:bg-surface-50!');

        // The slide and modal «×» is a bare 20 px icon; give it a 44 px touch target (owner, 2026-11-15: the phone filter
        // panel must be easy to close) without moving it.
        $closeButton = '-m-3 box-content size-5 cursor-pointer rounded-full p-3 text-ink-500 hover:bg-surface-50 hover:text-ink-900';
        TallStackUi::customize()->slide()->block('title.close', $closeButton);
        TallStackUi::customize()->modal()->block('title.close', $closeButton);

        // On phones every modal is a bottom sheet that can be dragged down to close (owner, 2026-11-15); its grab bar in
        // the design tokens. Storefront modals pass `handle`.
        TallStackUi::customize()->modal()->block('handle.bar', 'h-1 w-10 rounded-full bg-line-300');

        // The modal panel ships with `text-left`; the storefront is RTL, so align to the start side instead.
        TallStackUi::customize()
            ->modal()
            ->block('wrapper.fourth', 'relative flex w-full transform flex-col rounded-t-xl sm:rounded-xl bg-card text-start shadow-xl transition-all');
    }
}
