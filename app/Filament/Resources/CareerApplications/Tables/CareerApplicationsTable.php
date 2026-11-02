<?php

namespace App\Filament\Resources\CareerApplications\Tables;

use App\Filament\Resources\CareerApplications\CareerApplicationResource;
use App\Models\CareerApplication;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CareerApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('career'))
            ->columns([
                TextColumn::make('reference')->searchable()->copyable(),
                TextColumn::make('name')->searchable()->description(fn (CareerApplication $r) => $r->email),
                TextColumn::make('career.title')->label('Position')->searchable(),
                TextColumn::make('created_at')->label('Received')->date('j M Y')->sortable(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => CareerApplication::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'received' => 'info', 'under_review', 'shortlisted', 'interview' => 'warning',
                        'offered' => 'success', default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->options(CareerApplication::STATUSES),
                SelectFilter::make('career')->relationship('career', 'title')->label('Position'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                CareerApplicationResource::downloadCvAction(),
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                    ForceDeleteAction::make()
                        ->label('Erase permanently')
                        ->modalHeading('Erase application permanently')
                        ->modalDescription('This permanently deletes the application AND the applicant\'s CV file. Use it for data-subject erasure requests or at the end of the retention period. This cannot be undone.')
                        ->modalSubmitActionLabel('Yes, erase permanently'),
                ]),
            ]);
    }
}
