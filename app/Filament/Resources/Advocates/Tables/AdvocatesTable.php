<?php

namespace App\Filament\Resources\Advocates\Tables;

use App\Models\Advocate;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AdvocatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('photo_path')->label('')->disk('public')->circular(),
                TextColumn::make('name')->searchable()->sortable()->description(fn (Advocate $r) => $r->title),
                TextColumn::make('practiceAreas.name')->label('Practice areas')->badge()->limitList(2)->toggleable(),
                TextColumn::make('verification')->label('Credentials')->badge()
                    ->state(fn (Advocate $r) => $r->isVerified() ? 'Verified' : 'Unverified')
                    ->color(fn (string $state) => $state === 'Verified' ? 'success' : 'warning'),
                IconColumn::make('is_public')->label('On website')->boolean(),
                IconColumn::make('accepts_consultations')->label('Bookable')->boolean()->toggleable(),
                IconColumn::make('is_active')->label('Active')->boolean()->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('credentials_verified_at')->label('Verified')->nullable(),
                TernaryFilter::make('is_public')->label('On website'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    self::verifyAction(),
                    self::revokeAction(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function verifyAction(): Action
    {
        return Action::make('verifyCredentials')
            ->label('Verify credentials')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('Confirm you have checked this advocate\'s qualifications, memberships and practising status against authoritative sources, and that the advocate has authorised publication.')
            ->visible(fn (Advocate $record) => ! $record->isVerified() && auth()->user()->can('content.publish'))
            ->action(function (Advocate $record) {
                abort_unless(auth()->user()->can('content.publish'), 403);
                $record->markVerified(auth()->user());
                Notification::make()->title('Credentials verified')->success()->send();
            });
    }

    public static function revokeAction(): Action
    {
        return Action::make('revokeVerification')
            ->label('Revoke verification')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('The profile will be removed from the public website until verified again.')
            ->visible(fn (Advocate $record) => $record->isVerified() && auth()->user()->can('content.publish'))
            ->action(function (Advocate $record) {
                abort_unless(auth()->user()->can('content.publish'), 403);
                $record->revokeVerification();
                Notification::make()->title('Verification revoked')->warning()->send();
            });
    }
}
