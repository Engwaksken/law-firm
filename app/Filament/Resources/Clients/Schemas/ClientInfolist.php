<?php

namespace App\Filament\Resources\Clients\Schemas;

use App\Filament\Resources\Matters\MatterResource;
use App\Models\Client;
use App\Models\Matter;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ClientInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextEntry::make('reference')->badge(),
                TextEntry::make('type')->formatStateUsing(fn (string $state) => Client::TYPES[$state] ?? $state),
                TextEntry::make('status')->badge()->formatStateUsing(fn (string $state) => Client::STATUSES[$state] ?? $state),
                TextEntry::make('name'),
                TextEntry::make('email')->placeholder('—')->copyable(),
                TextEntry::make('phone')->placeholder('—'),
                TextEntry::make('registration_number')->placeholder('—')->visible(fn (Client $record) => $record->type === 'corporate'),
                TextEntry::make('address')->placeholder('—')->state(fn (Client $record) => collect([$record->address, $record->city, $record->country])->filter()->implode(', ')),
                TextEntry::make('responsibleUser.name')->label('Responsible advocate')->placeholder('—'),
                TextEntry::make('id_number')->label('Identification')
                    ->state(fn (Client $record) => $record->id_number
                        ? (Client::ID_TYPES[$record->id_type] ?? 'ID').': '.(auth()->user()->can('viewIdentification', $record) ? $record->id_number : $record->maskedIdNumber())
                        : null)
                    ->placeholder('Not recorded'),
            ]),
            Section::make('Matters')->columnSpanFull()->schema([
                // Only matters the viewer may see (others are not even counted).
                RepeatableEntry::make('visible_matters')->hiddenLabel()
                    ->state(fn (Client $record) => Matter::query()->visibleTo(auth()->user())->where('client_id', $record->id)->latest()->get()
                        ->map(fn (Matter $m) => ['label' => $m->label(), 'status' => $m->statusLabel(), 'url' => MatterResource::getUrl('view', ['record' => $m])])->all())
                    ->schema([
                        TextEntry::make('label')->hiddenLabel()->url(fn ($state, $record) => $record['url'] ?? null)->color('primary'),
                        TextEntry::make('status')->hiddenLabel()->badge(),
                    ])->columns(2)->placeholder('No matters you can access.'),
            ]),
        ]);
    }
}
