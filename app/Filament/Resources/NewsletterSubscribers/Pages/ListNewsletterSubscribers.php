<?php

namespace App\Filament\Resources\NewsletterSubscribers\Pages;

use App\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListNewsletterSubscribers extends ListRecords
{
    protected static string $resource = NewsletterSubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('Export active (CSV)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->visible(fn () => auth()->user()->can('content.manage'))
                ->action(function () {
                    abort_unless(auth()->user()->can('content.manage'), 403);
                    \App\Models\ActivityLog::record('newsletter.exported', description: 'Newsletter subscriber list exported');

                    return NewsletterSubscriberResource::exportActive();
                }),
        ];
    }
}
