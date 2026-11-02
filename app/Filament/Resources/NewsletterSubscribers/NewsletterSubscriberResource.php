<?php

namespace App\Filament\Resources\NewsletterSubscribers;

use App\Filament\Resources\NewsletterSubscribers\Pages\ListNewsletterSubscribers;
use App\Filament\Resources\NewsletterSubscribers\Tables\NewsletterSubscribersTable;
use App\Models\NewsletterSubscriber;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/** List-only: subscribers join through double opt-in on the website. */
class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Newsletter subscribers';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return NewsletterSubscribersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletterSubscribers::route('/'),
        ];
    }

    /** CSV of confirmed, not-unsubscribed subscribers. */
    public static function exportActive(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'name', 'confirmed_at']);
            NewsletterSubscriber::query()->active()->orderBy('email')->each(function (NewsletterSubscriber $s) use ($out) {
                // Prefix formula-leading values to prevent CSV injection in spreadsheet apps.
                $safe = fn (?string $v) => $v !== null && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
                fputcsv($out, [$safe($s->email), $safe($s->name), $s->confirmed_at?->toDateTimeString()]);
            });
            fclose($out);
        }, 'newsletter-subscribers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
