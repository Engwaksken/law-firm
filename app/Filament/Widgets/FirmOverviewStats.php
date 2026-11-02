<?php

namespace App\Filament\Widgets;

use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\Career;
use App\Models\CareerApplication;
use App\Models\Consultation;
use App\Models\ContactInquiry;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Headline numbers; each stat only appears for users allowed to see that data. */
class FirmOverviewStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $user = auth()->user();
        $stats = [];

        if ($user->can('consultations.view')) {
            $stats[] = Stat::make('Pending consultations', Consultation::where('status', 'pending')->count())
                ->description('Awaiting confirmation')->icon('heroicon-o-calendar-days')->color('warning');
        }

        if ($user->can('inquiries.manage')) {
            $stats[] = Stat::make('New inquiries', ContactInquiry::where('status', 'new')->count())
                ->description('From the website contact form')->icon('heroicon-o-inbox')->color('info');
        }

        if ($user->can('content.view') || $user->can('content.manage')) {
            $stats[] = Stat::make('Published articles', Article::where('status', 'published')->count())
                ->description(Article::where('status', 'draft')->count().' drafts')->icon('heroicon-o-newspaper');
        }

        if ($user->can('careers.manage')) {
            $stats[] = Stat::make('Open vacancies', Career::query()->open()->count())
                ->icon('heroicon-o-briefcase');
            $stats[] = Stat::make('New applications (7 days)', CareerApplication::where('created_at', '>=', now()->subDays(7))->count())
                ->icon('heroicon-o-document-text')->color('info');
        }

        if ($user->can('users.view') || $user->can('users.manage')) {
            $stats[] = Stat::make('Active staff accounts', User::where('is_active', true)->whereHas('roles', fn ($q) => $q->where('name', '!=', 'client'))->count())
                ->icon('heroicon-o-users');
        }

        if ($user->can('activity-logs.view')) {
            $failed = ActivityLog::where('event', 'auth.failed')->where('created_at', '>=', now()->subDay())->count();
            $stats[] = Stat::make('Failed sign-ins (24h)', $failed)
                ->description($failed > 10 ? 'Unusually high: review the audit log' : 'Within normal range')
                ->icon('heroicon-o-shield-exclamation')->color($failed > 10 ? 'danger' : 'success');
        }

        return $stats;
    }
}
