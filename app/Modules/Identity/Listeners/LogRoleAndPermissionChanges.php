<?php

declare(strict_types=1);

namespace App\Modules\Identity\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Audit trail (roadmap D16) for role assignments and permission changes, which spatie/laravel-permission
 * stores in pivot tables that model activity logging cannot see.
 */
final class LogRoleAndPermissionChanges
{
    public function handle(RoleAttachedEvent|RoleDetachedEvent|PermissionAttachedEvent|PermissionDetachedEvent $event): void
    {
        [$eventName, $key, $names] = match (true) {
            $event instanceof RoleAttachedEvent => ['roles_attached', 'roles', $this->names(Role::class, $event->rolesOrIds)],
            $event instanceof RoleDetachedEvent => ['roles_detached', 'roles', $this->names(Role::class, $event->rolesOrIds)],
            $event instanceof PermissionAttachedEvent => ['permissions_attached', 'permissions', $this->names(Permission::class, $event->permissionsOrIds)],
            $event instanceof PermissionDetachedEvent => ['permissions_detached', 'permissions', $this->names(Permission::class, $event->permissionsOrIds)],
        };

        if ($names === []) {
            return;
        }

        activity('admin')
            ->performedOn($event->model)
            ->event($eventName)
            ->withProperties([$key => $names])
            ->log($eventName);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return list<string>
     */
    private function names(string $modelClass, mixed $modelsOrIds): array
    {
        $items = $modelsOrIds instanceof Collection ? $modelsOrIds->all() : (array) $modelsOrIds;

        $ids = array_map(fn (mixed $item): mixed => $item instanceof Model ? $item->getKey() : $item, $items);

        /** @var list<string> $names */
        $names = $modelClass::query()->whereKey($ids)->orderBy('name')->pluck('name')->all();

        return $names;
    }
}
