<?php

declare(strict_types=1);

namespace App\Modules\Seo\Filament\Resources\SeoRedirects\Pages;

use App\Modules\Seo\Filament\Resources\SeoRedirects\SeoRedirectResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageSeoRedirects extends ManageRecords
{
    protected static string $resource = SeoRedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [SeoRedirectResource::createAction()];
    }
}
