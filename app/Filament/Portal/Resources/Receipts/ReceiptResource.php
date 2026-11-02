<?php

namespace App\Filament\Portal\Resources\Receipts;

use App\Filament\Portal\PortalResource;
use App\Filament\Portal\Resources\Invoices\InvoiceResource;
use App\Models\Payment;
use App\Models\Receipt;
use App\Support\Money;
use App\Support\PortalAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReceiptResource extends PortalResource
{
    protected static ?string $model = Receipt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Receipts';

    protected static ?string $slug = 'receipts';

    public static function portalQuery(): Builder
    {
        return PortalAccess::receipts(auth()->user())->with('payment.invoice');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->badge()->searchable(),
                TextColumn::make('issued_at')->label('Date')->date()->sortable(),
                TextColumn::make('payment.invoice.number')->label('Invoice'),
                TextColumn::make('payment.amount')->label('Amount')
                    ->formatStateUsing(fn ($state, Receipt $record) => Money::format($state, $record->payment->currency)),
                TextColumn::make('payment.method')->label('Method')->formatStateUsing(fn (?string $state) => Payment::METHODS[$state] ?? $state),
            ])
            ->defaultSort('issued_at', 'desc')
            ->recordActions([
                Action::make('pdf')->label('PDF')->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn () => InvoiceResource::pdfAvailable())
                    ->url(fn (Receipt $record) => route('filament.portal.files.receipt', $record)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListReceipts::route('/')];
    }
}
