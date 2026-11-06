<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\Roles\Pages;

use App\Modules\Identity\Filament\Resources\Roles\RoleResource;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\ListRoles as ShieldListRoles;

final class ListRoles extends ShieldListRoles
{
    protected static string $resource = RoleResource::class;
}
