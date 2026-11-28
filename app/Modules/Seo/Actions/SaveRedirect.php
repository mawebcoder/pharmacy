<?php

declare(strict_types=1);

namespace App\Modules\Seo\Actions;

use App\Modules\Seo\Data\SeoRedirectData;
use App\Modules\Seo\Enums\RedirectSource;
use App\Modules\Seo\Exceptions\RedirectRefused;
use App\Modules\Seo\Models\SeoRedirect;
use App\Modules\Seo\Support\SeoPath;
use Illuminate\Support\Facades\DB;

/**
 * Adds or changes a redirect (roadmap S38). Addresses are normalised (SeoPath); one redirect per old address (a new
 * one replaces it); the target is followed to its last stop, and redirects that pointed at the old address now point
 * there too, so a visitor is never sent through a chain. The home page, the admin and the tools cannot be redirected.
 */
final readonly class SaveRedirect
{
    private const array PROTECTED_PREFIXES = ['/admin', '/livewire', '/horizon', '/telescope', '/sitemap', '/robots.txt', '/up'];

    private const int MAX_CHAIN = 10;

    /**
     * @throws RedirectRefused
     */
    public function __invoke(SeoRedirectData $data, RedirectSource $source = RedirectSource::Admin, ?SeoRedirect $redirect = null): SeoRedirect
    {
        $from = SeoPath::normalize($data->fromPath);
        $to = SeoPath::target($data->toPath);

        if ($from === null || $to === null) {
            throw RedirectRefused::invalidAddress();
        }

        if ($from === '/' || array_filter(self::PROTECTED_PREFIXES, fn (string $prefix): bool => str_starts_with($from, $prefix)) !== []) {
            throw RedirectRefused::protectedPath($from);
        }

        $to = $this->lastStop($to, $from);

        return DB::transaction(function () use ($from, $to, $data, $source, $redirect): SeoRedirect {
            $redirect ??= SeoRedirect::query()->where(SeoRedirect::COLUMN_FROM_PATH, $from)->first() ?? new SeoRedirect;
            SeoRedirect::query()->where(SeoRedirect::COLUMN_FROM_PATH, $from)->whereKeyNot($redirect->getKey())->delete();

            $redirect->setFromPath($from)->setToPath($to)->setStatusCode($data->statusCode)->setSource($source)->save();

            // Old redirects to this address go straight to the new target.
            SeoRedirect::query()->where(SeoRedirect::COLUMN_TO_PATH, $from)->get()
                ->each(fn (SeoRedirect $earlier): bool => $earlier->setToPath($to)->save());

            return $redirect;
        });
    }

    /**
     * Follows existing redirects from the target; refuses one that comes back to the address being redirected.
     *
     * @throws RedirectRefused
     */
    private function lastStop(string $to, string $from): string
    {
        for ($step = 0; $step < self::MAX_CHAIN; $step++) {
            if ($to === $from) {
                throw RedirectRefused::loop();
            }

            $next = SeoRedirect::query()->where(SeoRedirect::COLUMN_FROM_PATH, $to)->value(SeoRedirect::COLUMN_TO_PATH);

            if (! is_string($next)) {
                return $to;
            }

            $to = $next;
        }

        throw RedirectRefused::loop();
    }
}
