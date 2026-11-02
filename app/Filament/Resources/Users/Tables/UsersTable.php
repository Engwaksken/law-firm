<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\ActivityLog;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()
                    ->description(fn (User $record) => $record->job_title),
                TextColumn::make('email')->searchable()->sortable()->copyable(),
                TextColumn::make('roles.name')->label('Roles')->badge()
                    ->formatStateUsing(fn (string $state) => \App\Support\Access::ROLES[$state] ?? $state),
                TextColumn::make('office.name')->label('Office')->toggleable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                IconColumn::make('mfa')->label('MFA')->boolean()
                    ->state(fn (User $record) => filled($record->app_authentication_secret)),
                TextColumn::make('last_login_at')->label('Last sign-in')->since()->sortable()->placeholder('Never'),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('roles')->relationship('roles', 'name')->multiple()->preload(),
                SelectFilter::make('office')->relationship('office', 'name'),
                TernaryFilter::make('is_active')->label('Active'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    Action::make('resetMfa')
                        ->label('Reset MFA')
                        ->icon(Heroicon::OutlinedKey)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('The user will have to set up their authenticator app again at next sign-in.')
                        ->visible(fn (User $record) => filled($record->app_authentication_secret) && auth()->user()->can('update', $record))
                        ->action(function (User $record) {
                            $record->forceFill(['app_authentication_secret' => null, 'app_authentication_recovery_codes' => null])->saveQuietly();
                            ActivityLog::record('auth.mfa-reset', $record, 'MFA reset by administrator');
                            Notification::make()->title('MFA reset')->success()->send();
                        }),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('deactivate')
                        ->label('Deactivate selected')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn () => auth()->user()->can('users.manage'))
                        ->action(function (Collection $records) {
                            $actor = auth()->user();
                            $count = 0;
                            foreach ($records as $user) {
                                if (! $user->is($actor) && $actor->can('update', $user)) {
                                    $user->update(['is_active' => false]);
                                    $count++;
                                }
                            }
                            Notification::make()->title("{$count} user(s) deactivated")->success()->send();
                        }),
                ]),
            ]);
    }
}
