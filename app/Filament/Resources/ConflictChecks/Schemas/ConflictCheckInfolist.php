<?php

namespace App\Filament\Resources\ConflictChecks\Schemas;

use App\Models\ConflictCheck;
use App\Models\ConflictCheckParty;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ConflictCheckInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('reference')->badge(),
                TextEntry::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => ConflictCheck::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'cleared' => 'success', 'conflict_found' => 'danger', 'waived' => 'warning', default => 'info' }),
                TextEntry::make('requester.name')->label('Run by'),
                TextEntry::make('created_at')->label('Run at')->dateTime(),
                TextEntry::make('client.name')->label('Client')->placeholder('—'),
                TextEntry::make('matter.reference')->label('Matter')->placeholder('—'),
                TextEntry::make('reviewer.name')->label('Reviewed by')->placeholder('Not yet reviewed'),
                TextEntry::make('reviewed_at')->dateTime()->placeholder('—'),
                TextEntry::make('review_notes')->label('Review reasoning')->columnSpanFull()->placeholder('—'),
            ]),
            Section::make('Names searched')->columnSpanFull()->collapsible()->schema([
                RepeatableEntry::make('parties')->hiddenLabel()->schema([
                    TextEntry::make('name')->hiddenLabel(),
                    TextEntry::make('role')->hiddenLabel()->badge()->formatStateUsing(fn (string $state) => ConflictCheckParty::ROLES[$state] ?? $state),
                ])->columns(2),
            ]),
            Section::make('Potential matches')
                ->description('Candidates only. A reviewer must decide whether any is a real conflict.')
                ->columnSpanFull()
                ->schema([
                    RepeatableEntry::make('results.hits')->hiddenLabel()
                        ->schema([
                            TextEntry::make('searched')->label('Searched name')
                                ->formatStateUsing(fn ($state, $record) => $state.' ('.(ConflictCheckParty::ROLES[$record['searched_role'] ?? ''] ?? 'other').')'),
                            TextEntry::make('matched_name')->label('Matched record'),
                            TextEntry::make('relationship')->label('Relationship'),
                            TextEntry::make('matter_reference')->label('Matter / client')
                                ->state(fn ($record) => trim(($record['matter_reference'] ?? $record['client_reference'] ?? '').' '.(($record['restricted'] ?? false) ? '(restricted, ask '.($record['contact'] ?? 'the lead advocate').')' : ($record['matter_title'] ?? '')))),
                            TextEntry::make('score')->label('Match')->badge()
                                ->formatStateUsing(fn ($state, $record) => ($record['match'] ?? '') === 'exact' ? 'Exact' : "Similar ({$state}%)")
                                ->color(fn ($state) => (int) $state === 100 ? 'danger' : 'warning'),
                        ])
                        ->columns(5)
                        ->placeholder('No potential matches found.'),
                ]),
        ]);
    }
}
