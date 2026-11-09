<?php

declare(strict_types=1);

namespace App\Modules\Identity\Queries;

use App\Modules\Identity\Models\AdminUser;

/**
 * Admin names for "who changed it" columns in other modules' history tables.
 */
final readonly class AdminNames
{
    /**
     * @param  list<int>  $adminIds
     * @return array<int, string> admin id => name
     */
    public function for(array $adminIds): array
    {
        if ($adminIds === []) {
            return [];
        }

        /** @var array<int, string> $names */
        $names = AdminUser::query()->whereKey(array_values(array_unique($adminIds)))->pluck(AdminUser::COLUMN_NAME, AdminUser::COLUMN_ID)->all();

        return $names;
    }
}
