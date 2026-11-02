<?php

namespace App\Filament\Resources\ContactInquiries\Tables;

use App\Models\ContactInquiry;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ContactInquiriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Received')->since()->sortable(),
                TextColumn::make('name')->searchable()->description(fn (ContactInquiry $r) => $r->email),
                TextColumn::make('subject')->limit(50)->placeholder('—')->searchable(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => ContactInquiry::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => ['new' => 'info', 'in_progress' => 'warning', 'handled' => 'success', 'spam' => 'gray'][$state] ?? 'gray'),
                TextColumn::make('handler.name')->label('Handled by')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(ContactInquiry::STATUSES),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    self::statusAction('markInProgress', 'Mark in progress', 'in_progress', Heroicon::OutlinedClock, 'warning'),
                    self::statusAction('markHandled', 'Mark handled', 'handled', Heroicon::OutlinedCheckCircle, 'success'),
                    self::statusAction('markSpam', 'Mark as spam', 'spam', Heroicon::OutlinedNoSymbol, 'gray'),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ]);
    }

    private static function statusAction(string $name, string $label, string $status, Heroicon $icon, string $color): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->visible(fn (ContactInquiry $record) => $record->status !== $status && auth()->user()->can('update', $record))
            ->action(function (ContactInquiry $record) use ($status) {
                abort_unless(auth()->user()->can('update', $record), 403);
                $record->update([
                    'status' => $status,
                    'handled_at' => $status === 'handled' ? now() : $record->handled_at,
                    'handled_by' => in_array($status, ['handled', 'in_progress'], true) ? auth()->id() : $record->handled_by,
                ]);
            });
    }
}
