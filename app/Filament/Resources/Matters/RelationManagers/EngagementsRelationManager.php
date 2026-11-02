<?php

namespace App\Filament\Resources\Matters\RelationManagers;

use App\Legal\MatterLifecycle;
use App\Models\Engagement;
use App\Models\Matter;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Engagement terms. Approval by someone other than the submitter, after conflict clearance. */
class EngagementsRelationManager extends RelationManager
{
    protected static string $relationship = 'engagements';

    protected static ?string $title = 'Engagement';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('fee_arrangement')->options(Engagement::FEE_ARRANGEMENTS)->required()->live(),
            TextInput::make('currency')->default(fn () => Settings::get('default_currency'))->required()->length(3),
            TextInput::make('hourly_rate')->numeric()->minValue(0)->visible(fn (Get $get) => $get('fee_arrangement') === 'hourly')->required(fn (Get $get) => $get('fee_arrangement') === 'hourly'),
            TextInput::make('fixed_fee')->numeric()->minValue(0)->visible(fn (Get $get) => $get('fee_arrangement') === 'fixed')->required(fn (Get $get) => $get('fee_arrangement') === 'fixed'),
            TextInput::make('retainer_amount')->numeric()->minValue(0)->visible(fn (Get $get) => in_array($get('fee_arrangement'), ['retainer', 'hourly'], true)),
            DatePicker::make('start_date'),
            Textarea::make('scope')->label('Scope of engagement')->required()->rows(4)->columnSpanFull(),
            Textarea::make('terms')->label('Key terms')->rows(3)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        /** @var Matter $matter */
        $matter = $this->getOwnerRecord();
        $lifecycle = app(MatterLifecycle::class);

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['submitter', 'approver']))
            ->columns([
                TextColumn::make('fee_arrangement')->label('Fees')->formatStateUsing(fn (string $state) => Engagement::FEE_ARRANGEMENTS[$state] ?? $state)
                    ->description(fn (Engagement $record) => match ($record->fee_arrangement) {
                        'hourly' => $record->currency.' '.number_format((float) $record->hourly_rate).' / hour',
                        'fixed' => $record->currency.' '.number_format((float) $record->fixed_fee),
                        'retainer' => $record->currency.' '.number_format((float) $record->retainer_amount).' retainer',
                        default => null,
                    }),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => Engagement::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'approved' => 'success', 'declined' => 'danger', 'pending_approval' => 'warning', default => 'gray' }),
                TextColumn::make('submitter.name')->label('Submitted by')->placeholder('—'),
                TextColumn::make('approver.name')->label('Decided by')->placeholder('—'),
                TextColumn::make('approved_at')->label('Decided')->dateTime()->placeholder('—'),
                TextColumn::make('decision_notes')->label('Notes')->limit(40)->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()->label('Draft engagement')
                    ->visible(fn () => auth()->user()->can('update', $matter) && ! in_array($matter->status, ['closed', 'archived'], true))
                    ->authorize(fn () => auth()->user()->can('update', $matter))
                    ->mutateDataUsing(fn (array $data) => $data + ['client_id' => $matter->client_id]),
            ])
            ->recordActions([
                Action::make('submit')->label('Submit for approval')->icon(Heroicon::OutlinedPaperAirplane)->color('info')
                    ->visible(fn (Engagement $record) => auth()->user()->can('update', $record))
                    ->requiresConfirmation()
                    ->action(function (Engagement $record) use ($lifecycle) {
                        $lifecycle->submitEngagement($record, auth()->user());
                        Notification::make()->title('Submitted for approval')->success()->send();
                    }),
                Action::make('approve')->icon(Heroicon::OutlinedCheckBadge)->color('success')
                    ->visible(fn (Engagement $record) => auth()->user()->can('approve', $record))
                    ->schema([Textarea::make('notes')->label('Notes (optional)')->rows(2)])
                    ->action(function (Engagement $record, array $data) use ($lifecycle) {
                        $lifecycle->decideEngagement($record, auth()->user(), true, $data['notes'] ?? null);
                        Notification::make()->title('Engagement approved')->success()->send();
                    }),
                Action::make('decline')->icon(Heroicon::OutlinedXCircle)->color('danger')
                    ->visible(fn (Engagement $record) => auth()->user()->can('approve', $record))
                    ->schema([Textarea::make('notes')->label('Reason')->required()->rows(3)])
                    ->action(function (Engagement $record, array $data) use ($lifecycle) {
                        $lifecycle->decideEngagement($record, auth()->user(), false, $data['notes']);
                        Notification::make()->title('Engagement declined')->send();
                    }),
                EditAction::make()->visible(fn (Engagement $record) => auth()->user()->can('update', $record))
                    ->authorize(fn (Engagement $record) => auth()->user()->can('update', $record)),
                DeleteAction::make()->visible(fn (Engagement $record) => auth()->user()->can('delete', $record))
                    ->authorize(fn (Engagement $record) => auth()->user()->can('delete', $record)),
            ]);
    }
}
