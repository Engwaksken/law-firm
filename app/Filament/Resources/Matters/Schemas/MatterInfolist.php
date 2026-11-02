<?php

namespace App\Filament\Resources\Matters\Schemas;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\Matter;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MatterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('reference')->badge()->copyable(),
                TextEntry::make('status')->badge()->formatStateUsing(fn (string $state) => Matter::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'active' => 'success', 'stayed', 'pending' => 'warning', 'closed', 'archived' => 'gray', default => 'info' }),
                TextEntry::make('priority')->badge()->formatStateUsing(fn (string $state) => Matter::PRIORITIES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) { 'urgent' => 'danger', 'high' => 'warning', default => 'gray' }),
                IconEntry::make('is_restricted')->label('Restricted')->boolean(),
                TextEntry::make('title')->columnSpan(2),
                TextEntry::make('client.name')->label('Client')
                    ->url(fn (Matter $record) => auth()->user()->can('view', $record->client) ? ClientResource::getUrl('view', ['record' => $record->client]) : null)
                    ->color('primary'),
                TextEntry::make('lead.name')->label('Lead advocate')->placeholder('—'),
                TextEntry::make('practiceArea.name')->label('Practice area')->placeholder('—'),
                TextEntry::make('case_type')->placeholder('—'),
                TextEntry::make('court.name')->label('Court')->placeholder('—')
                    ->state(fn (Matter $record) => $record->court?->label()),
                TextEntry::make('case_number')->placeholder('—'),
                TextEntry::make('filing_date')->date()->placeholder('—'),
                TextEntry::make('opened_at')->label('Active since')->date()->placeholder('—'),
                TextEntry::make('closed_at')->date()->placeholder('—'),
                TextEntry::make('closure_reason')->placeholder('—'),
                TextEntry::make('description')->label('Summary')->columnSpanFull()->placeholder('—')->prose(),
            ]),
        ]);
    }
}
