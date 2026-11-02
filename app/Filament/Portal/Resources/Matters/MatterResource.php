<?php

namespace App\Filament\Portal\Resources\Matters;

use App\Filament\Portal\PortalResource;
use App\Filament\Portal\Resources\DocumentRequests\DocumentRequestResource;
use App\Filament\Portal\Resources\Documents\DocumentResource;
use App\Models\ClientUpdate;
use App\Models\Matter;
use App\Support\PortalAccess;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The client's view of a matter: status and released information only.
 * Deliberately excludes case notes, the internal summary, parties' notes,
 * team members other than the lead, status-change reasons and anything unpublished.
 */
class MatterResource extends PortalResource
{
    protected static ?string $model = Matter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'My matters';

    protected static ?string $slug = 'matters';

    public static function portalQuery(): Builder
    {
        return PortalAccess::matters(auth()->user())->with(['lead', 'practiceArea', 'court']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->badge()->searchable(),
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => Matter::STATUSES[$state] ?? $state),
                TextColumn::make('lead.name')->label('Advocate')->placeholder('—'),
                TextColumn::make('updated_at')->label('Last updated')->since(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([ViewAction::make()])
            ->recordUrl(fn (Matter $record) => static::getUrl('view', ['record' => $record]));
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextEntry::make('reference')->badge(),
                TextEntry::make('status')->badge()->formatStateUsing(fn (string $state) => Matter::STATUSES[$state] ?? $state),
                TextEntry::make('lead.name')->label('Your advocate')->placeholder('—'),
                TextEntry::make('title')->columnSpan(2),
                TextEntry::make('practiceArea.name')->label('Practice area')->placeholder('—'),
                TextEntry::make('court')->label('Court')->state(fn (Matter $record) => $record->court?->label())->placeholder('—'),
                TextEntry::make('case_number')->placeholder('—'),
                TextEntry::make('opened_at')->label('Opened')->date()->placeholder('—'),
                TextEntry::make('closed_at')->label('Closed')->date()->placeholder('—'),
            ]),
            Section::make('Case updates')->columnSpanFull()->schema([
                RepeatableEntry::make('portal_updates')->hiddenLabel()
                    ->state(fn (Matter $record) => PortalAccess::updates(auth()->user())->where('matter_id', $record->id)
                        ->latest('published_at')->get()
                        ->map(fn (ClientUpdate $update) => [
                            'title' => $update->title,
                            'published_at' => $update->published_at?->format('j M Y'),
                            'body' => $update->body,
                        ])->all())
                    ->schema([
                        TextEntry::make('title')->hiddenLabel()->weight('bold'),
                        TextEntry::make('published_at')->hiddenLabel()->color('gray'),
                        TextEntry::make('body')->hiddenLabel()->columnSpanFull()
                            ->formatStateUsing(fn (?string $state) => nl2br(e((string) $state)))->html(),
                    ])->columns(2)
                    ->placeholder('No updates have been published yet.'),
            ]),
            Section::make('Documents')->columnSpanFull()->schema([
                TextEntry::make('documents_link')->hiddenLabel()
                    ->state(fn (Matter $record) => trans_choice(':count shared document|:count shared documents',
                        PortalAccess::documents(auth()->user())->where('matter_id', $record->id)->count()))
                    ->url(fn (Matter $record) => DocumentResource::getUrl('index', ['filters' => ['matter' => ['value' => $record->id]]]))
                    ->color('primary'),
                TextEntry::make('requests_link')->hiddenLabel()
                    ->state(fn (Matter $record) => trans_choice(':count document requested from you|:count documents requested from you',
                        PortalAccess::documentRequests(auth()->user())->where('matter_id', $record->id)->where('status', 'open')->count()))
                    ->url(fn () => DocumentRequestResource::getUrl())
                    ->color('primary'),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMatters::route('/'),
            'view' => Pages\ViewMatter::route('/{record}'),
        ];
    }
}
