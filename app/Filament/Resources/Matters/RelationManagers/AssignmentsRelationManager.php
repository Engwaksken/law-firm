<?php

namespace App\Filament\Resources\Matters\RelationManagers;

use App\Models\Matter;
use App\Models\MatterAssignment;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

/** The matter team. Membership grants access, so every change is authorised and audited. */
class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Team';

    private function canManage(): bool
    {
        return auth()->user()->can('assign', $this->getOwnerRecord());
    }

    public function form(Schema $schema): Schema
    {
        /** @var Matter $matter */
        $matter = $this->getOwnerRecord();

        return $schema->components([
            Select::make('user_id')->label('Staff member')->required()->searchable()
                ->options(fn () => User::query()->where('is_active', true)->permission('matters.view-assigned')->orderBy('name')->pluck('name', 'id'))
                ->rule(fn (?MatterAssignment $record) => Rule::unique('matter_assignments', 'user_id')->where('matter_id', $matter->id)->ignore($record?->id))
                ->disabledOn('edit'),
            Select::make('role')->options(Matter::ASSIGNMENT_ROLES)->default('associate')->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.name')
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'assigner']))
            ->columns([
                TextColumn::make('user.name')->label('Name'),
                TextColumn::make('role')->badge()->formatStateUsing(fn (string $state) => Matter::ASSIGNMENT_ROLES[$state] ?? $state),
                TextColumn::make('assigner.name')->label('Added by')->placeholder('—'),
                TextColumn::make('created_at')->label('Since')->date(),
            ])
            ->headerActions([
                CreateAction::make()->label('Add team member')
                    ->visible(fn () => $this->canManage())
                    ->authorize(fn () => $this->canManage())
                    ->mutateDataUsing(fn (array $data) => $data + ['assigned_by' => auth()->id()]),
            ])
            ->recordActions([
                EditAction::make()->visible(fn () => $this->canManage())->authorize(fn () => $this->canManage()),
                // The lead can't be removed here; change the lead on the matter instead.
                DeleteAction::make()->label('Remove')
                    ->visible(fn (MatterAssignment $record) => $this->canManage() && $record->user_id !== $this->getOwnerRecord()->lead_user_id)
                    ->authorize(fn (MatterAssignment $record) => $this->canManage() && $record->user_id !== $this->getOwnerRecord()->lead_user_id),
            ]);
    }
}
