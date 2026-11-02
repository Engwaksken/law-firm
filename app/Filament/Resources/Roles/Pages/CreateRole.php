<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        [$data, $permissionIds] = RoleForm::extractPermissions($data);

        $role = static::getModel()::create($data + ['guard_name' => 'web']);
        $role->syncPermissions($permissionIds);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    }
}
