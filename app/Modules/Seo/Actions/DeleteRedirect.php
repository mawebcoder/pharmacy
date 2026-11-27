<?php

declare(strict_types=1);

namespace App\Modules\Seo\Actions;

use App\Modules\Seo\Models\SeoRedirect;

final readonly class DeleteRedirect
{
    public function __invoke(SeoRedirect $redirect): void
    {
        $redirect->delete();
    }
}
