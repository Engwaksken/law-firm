<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Role;
use App\Models\User;
use App\Support\Access;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profile')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                    TextInput::make('phone')->tel()->maxLength(50),
                    TextInput::make('job_title')->maxLength(255),
                    Select::make('office_id')->label('Office')->relationship('office', 'name')->searchable()->preload()->live(),
                    Select::make('department_id')->label('Department')
                        ->relationship('department', 'name', fn ($query, Get $get) => $query
                            ->when($get('office_id'), fn ($q, $office) => $q->where(fn ($q) => $q->where('office_id', $office)->orWhereNull('office_id'))))
                        ->searchable()->preload(),
                ]),

            Section::make('Access')
                ->columns(2)
                ->schema([
                    Select::make('roles')
                        ->relationship('roles', 'name')
                        ->multiple()
                        ->preload()
                        ->options(fn () => Access::assignableRoles(auth()->user())->mapWithKeys(fn (Role $r) => [$r->id => $r->label]))
                        ->getOptionLabelFromRecordUsing(fn (Role $record) => $record->label)
                        ->rules([self::onlyAssignableRoles()])
                        ->helperText('You can only grant roles whose permissions you hold yourself.')
                        ->columnSpanFull(),
                    Toggle::make('is_active')->label('Account active')->default(true)
                        ->helperText('Inactive users cannot sign in.'),
                ]),

            Section::make('Password')
                ->columns(2)
                ->description(fn (string $operation) => $operation === 'edit' ? 'Leave blank to keep the current password.' : null)
                ->schema([
                    TextInput::make('password')
                        ->password()->revealable()
                        ->rule(Password::default())
                        ->required(fn (string $operation) => $operation === 'create')
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->confirmed(),
                    TextInput::make('password_confirmation')->password()->revealable()
                        ->requiredWith('password')->dehydrated(false),
                ]),
        ]);
    }

    /** Server-side guard: the submitted role IDs must all be grantable by the current user. */
    private static function onlyAssignableRoles(): Closure
    {
        return fn (?User $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record) {
            $actor = auth()->user();
            $allowed = Access::assignableRoles($actor)->pluck('id')->map(fn ($id) => (string) $id);
            $current = $record?->roles->pluck('id')->map(fn ($id) => (string) $id) ?? collect();

            // Roles the user already had may stay even if the actor couldn't grant them.
            $added = collect($value ?? [])->map(fn ($id) => (string) $id)->diff($current);
            $removed = $current->diff(collect($value ?? [])->map(fn ($id) => (string) $id));

            if ($added->diff($allowed)->isNotEmpty() || $removed->diff($allowed)->isNotEmpty()) {
                $fail('You are not allowed to grant or remove one or more of the selected roles.');
            }
        };
    }
}
