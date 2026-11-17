<?php

declare(strict_types=1);

namespace App\Modules\Settings\Actions;

use App\Modules\Settings\Models\Pharmacist;

/**
 * Removes a pharmacist and their photo (roadmap S35); without a pharmacist in charge, product pages leave the line out.
 */
final class DeletePharmacist
{
    public function __invoke(Pharmacist $pharmacist): void
    {
        $pharmacist->delete();
    }
}
