<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Access;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $permissionSections = collect(Access::PERMISSIONS)->map(fn (array $permissions, string $group) => Section::make($group)
            ->compact()
            ->schema([
                CheckboxList::make('permissions_'.str($group)->slug('_'))
                    ->hiddenLabel()
                    ->options(fn () => Permission::query()->whereIn('name', array_keys($permissions))->pluck('name', 'id')
                        ->map(fn ($name) => $permissions[$name]))
                    ->afterStateHydrated(fn (CheckboxList $component, ?Role $record) => $component->state(
                        $record?->permissions->whereIn('name', array_keys($permissions))->pluck('id')->map(fn ($id) => (string) $id)->values()->all() ?? []
                    ))
                    ->bulkToggleable()
                    ->columns(2)
                    ->dehydrated(),
            ]))->values()->all();

        return $schema->components([
            Section::make('Role')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->required()->maxLength(100)
                        ->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->disabled(fn (?Role $record) => $record?->isSystemRole() ?? false)
                        ->helperText(fn (?Role $record) => $record?->isSystemRole() ? 'Built-in role names are used by the system and cannot be changed.' : 'Lowercase with dashes, e.g. "intern".'),
                    TextInput::make('description')->maxLength(255),
                    Hidden::make('guard_name')->default('web'),
                ]),
            Section::make('Permissions')
                ->description('Users with this role receive every permission ticked below.')
                ->columnSpanFull()
                ->schema($permissionSections),
        ]);
    }

    /**
     * Collect the per-group checkbox lists into a flat list of permission IDs.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: list<int>}
     */
    public static function extractPermissions(array $data): array
    {
        $ids = [];
        foreach (array_keys($data) as $key) {
            if (str_starts_with($key, 'permissions_')) {
                $ids = array_merge($ids, array_map('intval', (array) $data[$key]));
                unset($data[$key]);
            }
        }

        return [$data, array_values(array_unique($ids))];
    }
}
