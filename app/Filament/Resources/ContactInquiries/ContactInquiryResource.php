<?php

namespace App\Filament\Resources\ContactInquiries;

use App\Filament\Resources\ContactInquiries\Pages\ListContactInquiries;
use App\Filament\Resources\ContactInquiries\Tables\ContactInquiriesTable;
use App\Models\ContactInquiry;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ContactInquiryResource extends Resource
{
    protected static ?string $model = ContactInquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'Inbox';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Contact inquiries';

    protected static ?string $recordTitleAttribute = 'subject';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! auth()->user()?->can('inquiries.manage')) {
            return null;
        }
        $count = ContactInquiry::where('status', 'new')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextEntry::make('created_at')->label('Received')->dateTime(),
                TextEntry::make('status')->badge()->formatStateUsing(fn (string $state) => ContactInquiry::STATUSES[$state] ?? $state),
                TextEntry::make('name'),
                TextEntry::make('email')->copyable(),
                TextEntry::make('phone')->placeholder('—'),
                TextEntry::make('subject')->placeholder('—'),
                TextEntry::make('message')->columnSpanFull()->prose(),
                TextEntry::make('handler.name')->label('Handled by')->placeholder('—'),
                TextEntry::make('handled_at')->dateTime()->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return ContactInquiriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactInquiries::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
