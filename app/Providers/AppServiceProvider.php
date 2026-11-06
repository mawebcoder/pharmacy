<?php

declare(strict_types=1);

namespace App\Providers;

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
    }

    /**
     * Storefront UI kit adjustments (roadmap B10). Colours come from the B3 tokens in resources/css/app.css.
     */
    protected function customizeTallStackUi(): void
    {
        // The dialog's cancel action is a neutral secondary button, not a red one (one strong CTA per view).
        TallStackUi::customize()
            ->dialog()
            ->block('buttons.cancel.base', 'w-full text-sm focus:ring-0! focus:ring-offset-0! bg-white! text-ink-900! ring-1 ring-line-200! hover:bg-surface-50!');
    }
}
