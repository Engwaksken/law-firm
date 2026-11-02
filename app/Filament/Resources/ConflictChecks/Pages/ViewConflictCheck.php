<?php

namespace App\Filament\Resources\ConflictChecks\Pages;

use App\Filament\Resources\ConflictChecks\ConflictCheckResource;
use App\Legal\ConflictSearch;
use App\Models\ConflictCheck;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/** @property ConflictCheck $record */
class ViewConflictCheck extends ViewRecord
{
    protected static string $resource = ConflictCheckResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('review')
                ->label('Record decision')->icon(Heroicon::OutlinedScale)->color('warning')
                ->visible(fn () => auth()->user()->can('review', $this->record))
                ->schema([
                    Radio::make('decision')->required()->options([
                        'cleared' => 'No conflict: cleared',
                        'conflict_found' => 'Conflict: the firm cannot act',
                        'waived' => 'Conflict waived (informed written consent obtained)',
                    ]),
                    Textarea::make('notes')->label('Reasoning')->required()->rows(5)
                        ->helperText('Explain your assessment of each potential match. This record supports the firm\'s professional obligations.'),
                ])
                ->action(function (array $data) {
                    app(ConflictSearch::class)->review($this->record, auth()->user(), $data['decision'], $data['notes']);
                    Notification::make()->title('Decision recorded')->success()->send();
                    $this->refreshFormData([]);
                }),
        ];
    }
}
