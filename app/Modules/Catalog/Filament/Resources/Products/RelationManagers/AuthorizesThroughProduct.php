<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\Products\RelationManagers;

use Illuminate\Database\Eloquent\Model;

/**
 * Variants, FAQs and links are part of the product: seeing them needs "view" and changing them needs "update" on
 * the product (ProductPolicy).
 */
trait AuthorizesThroughProduct
{
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth('admin')->user()?->can('view', $ownerRecord) ?? false;
    }

    public function isReadOnly(): bool
    {
        return ! (auth('admin')->user()?->can('update', $this->getOwnerRecord()) ?? false);
    }

    protected function canChange(): bool
    {
        return ! $this->isReadOnly();
    }
}
