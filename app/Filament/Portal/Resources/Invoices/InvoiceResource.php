<?php

namespace App\Filament\Portal\Resources\Invoices;

use App\Filament\Portal\PortalResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Support\Money;
use App\Support\PortalAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Issued invoices only (never drafts, pending approval or void), with internal notes hidden. */
class InvoiceResource extends PortalResource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Invoices';

    protected static ?string $slug = 'invoices';

    public static function portalQuery(): Builder
    {
        return PortalAccess::invoices(auth()->user())->with(['matter', 'client']);
    }

    public static function pdfAvailable(): bool
    {
        return class_exists(\App\Finance\FinancePdf::class);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable()->badge(),
                TextColumn::make('matter.reference')->label('Matter'),
                TextColumn::make('issue_date')->date()->sortable(),
                TextColumn::make('due_date')->date()->sortable(),
                TextColumn::make('total')->formatStateUsing(fn ($state, Invoice $record) => Money::format($state, $record->currency)),
                TextColumn::make('balance')->formatStateUsing(fn ($state, Invoice $record) => Money::format($state, $record->currency)),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => Invoice::STATUSES[$state] ?? $state)
                    ->color(fn (string $state, Invoice $record) => $record->isOverdue() ? 'danger' : match ($state) { 'paid' => 'success', 'partially_paid' => 'warning', default => 'info' }),
            ])
            ->defaultSort('issue_date', 'desc')
            ->filters([
                SelectFilter::make('status')->options(collect(Invoice::STATUSES)->only(Invoice::ISSUED_STATUSES)->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('pdf')->label('PDF')->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn () => static::pdfAvailable())
                    ->url(fn (Invoice $record) => route('filament.portal.files.invoice', $record)),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('number')->badge(),
                TextEntry::make('status')->badge()->formatStateUsing(fn (string $state) => Invoice::STATUSES[$state] ?? $state),
                TextEntry::make('issue_date')->date(),
                TextEntry::make('due_date')->date(),
                TextEntry::make('matter.reference')->label('Matter'),
                TextEntry::make('total')->formatStateUsing(fn ($state, Invoice $record) => Money::format($state, $record->currency)),
                TextEntry::make('amount_paid')->label('Paid')->formatStateUsing(fn ($state, Invoice $record) => Money::format($state, $record->currency)),
                TextEntry::make('balance')->label('Balance due')->weight('bold')->formatStateUsing(fn ($state, Invoice $record) => Money::format($state, $record->currency)),
                TextEntry::make('notes')->columnSpanFull()->placeholder('—'),
            ]),
            Section::make('Items')->columnSpanFull()->schema([
                RepeatableEntry::make('items')->hiddenLabel()->schema([
                    TextEntry::make('description')->hiddenLabel()->columnSpan(3),
                    TextEntry::make('quantity')->label('Qty'),
                    TextEntry::make('unit_price')->label('Unit price')->formatStateUsing(fn ($state, InvoiceItem $record) => Money::format($state, $record->invoice->currency)),
                    TextEntry::make('line_total')->label('Amount')->formatStateUsing(fn ($state, InvoiceItem $record) => Money::format($state, $record->invoice->currency)),
                ])->columns(6),
            ]),
            Section::make('Payments')->columnSpanFull()->schema([
                RepeatableEntry::make('portal_payments')->hiddenLabel()
                    ->state(fn (Invoice $record) => PortalAccess::payments(auth()->user())->where('invoice_id', $record->id)->with('receipt')->get()
                        ->map(fn (Payment $payment) => [
                            'date' => $payment->received_on->format('j M Y'),
                            'amount' => Money::format($payment->amount, $payment->currency),
                            'method' => Payment::METHODS[$payment->method] ?? $payment->method,
                            'receipt' => $payment->receipt?->number,
                            'receipt_url' => $payment->receipt && static::pdfAvailable() ? route('filament.portal.files.receipt', $payment->receipt) : null,
                        ])->all())
                    ->schema([
                        TextEntry::make('date')->hiddenLabel(),
                        TextEntry::make('amount')->hiddenLabel(),
                        TextEntry::make('method')->hiddenLabel(),
                        TextEntry::make('receipt')->hiddenLabel()->placeholder('—')
                            ->url(fn ($record) => $record['receipt_url'] ?? null)->color('primary'),
                    ])->columns(4)
                    ->placeholder('No payments recorded yet.'),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'view' => Pages\ViewInvoice::route('/{record}'),
        ];
    }
}
