<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\Roles\Pages;

use App\Modules\Identity\Filament\Resources\Roles\RoleResource;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\ViewRole as ShieldViewRole;

final class ViewRole extends ShieldViewRole
{
    protected static string $resource = RoleResource::class;
}
