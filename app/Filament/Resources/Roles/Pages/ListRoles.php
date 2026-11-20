<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Spatie\Permission\PermissionRegistrar;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(function (array $data) {
                [$data, $permissionIds] = RoleForm::extractPermissions($data);
                $role = \App\Models\Role::create($data + ['guard_name' => 'web']);
                $role->syncPermissions($permissionIds);
                app(PermissionRegistrar::class)->forgetCachedPermissions();

                return $role;
            }),
        ];
    }
}
