<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\PermissionAttached;
use Spatie\Permission\Events\PermissionDetached;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;

/** Audit role assignments to users and permission grants to roles. */
class RecordRoleAndPermissionChanges
{
    public function handle(RoleAttached|RoleDetached|PermissionAttached|PermissionDetached $event): void
    {
        [$eventName, $names] = match (true) {
            $event instanceof RoleAttached => ['access.role-assigned', $this->names(Role::class, $event->rolesOrIds)],
            $event instanceof RoleDetached => ['access.role-removed', $this->names(Role::class, $event->rolesOrIds)],
            $event instanceof PermissionAttached => ['access.permission-granted', $this->names(Permission::class, $event->permissionsOrIds)],
            $event instanceof PermissionDetached => ['access.permission-revoked', $this->names(Permission::class, $event->permissionsOrIds)],
        };

        if ($names === []) {
            return;
        }

        ActivityLog::record($eventName, $event->model, class_basename($event->model).' '.str($eventName)->after('.')->replace('-', ' '), ['names' => $names]);
    }

    /** @return list<string> */
    private function names(string $modelClass, mixed $items): array
    {
        // Spatie passes a single model, an id, an array or a collection. Don't (array)-cast
        // a model: that yields its internal properties.
        $items = match (true) {
            $items instanceof Collection => $items->all(),
            is_array($items) => $items,
            default => [$items],
        };
        $ids = [];
        $names = [];
        foreach ($items as $item) {
            is_object($item) ? $names[] = $item->name : $ids[] = $item;
        }
        if ($ids !== []) {
            $names = array_merge($names, $modelClass::query()->whereIn('id', $ids)->pluck('name')->all());
        }

        return array_values(array_unique($names));
    }
}
