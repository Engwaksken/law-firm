<?php

namespace App\Filament\Resources\Consultations\Tables;

use App\Filament\Resources\Consultations\ConsultationResource;
use App\Models\Consultation;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ConsultationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['practiceArea', 'advocate']))
            ->columns([
                TextColumn::make('reference')->searchable()->copyable(),
                TextColumn::make('name')->searchable()->description(fn (Consultation $r) => $r->email),
                TextColumn::make('practiceArea.name')->label('Practice area')->placeholder('—')->toggleable(),
                TextColumn::make('consultation_type')->label('Format')->formatStateUsing(fn (?string $state) => Consultation::TYPES[$state] ?? $state)->toggleable(),
                TextColumn::make('preferred_date')->label('Preferred')->date('j M Y')->placeholder('—')->sortable(),
                TextColumn::make('scheduled_at')->label('Scheduled')->dateTime('j M Y H:i')->placeholder('—')->sortable(),
                TextColumn::make('advocate.name')->label('Advocate')->placeholder('—'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => Consultation::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => ['pending' => 'warning', 'confirmed' => 'success', 'declined' => 'danger', 'completed' => 'gray', 'cancelled' => 'gray'][$state] ?? 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options(Consultation::STATUSES),
                SelectFilter::make('practiceArea')->relationship('practiceArea', 'name'),
                SelectFilter::make('advocate')->relationship('advocate', 'name'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                ConsultationResource::confirmAction(),
                ActionGroup::make([
                    ConsultationResource::declineAction(),
                    EditAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ]);
    }
}
