<?php

namespace App\Filament\Portal\Pages;

use App\Models\Appointment;
use App\Models\ClientMessage;
use App\Models\Hearing;
use App\Support\Money;
use App\Support\PortalAccess;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Collection;

/**
 * Client home. Shows only portal-released facts: matter status, dates of
 * upcoming hearings/appointments (no notes or outcomes), issued invoices,
 * unread firm messages and open document requests.
 */
class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.portal.dashboard';

    protected static ?string $title = 'Overview';

    public function getHeading(): string
    {
        return 'Welcome, '.auth()->user()->name;
    }

    public function getColumns(): int|array
    {
        return 1;
    }

    public function getWidgets(): array
    {
        return [];
    }

    public function matters(): Collection
    {
        return PortalAccess::matters(auth()->user())->with('lead')->latest('updated_at')->limit(10)->get();
    }

    /** @return Collection<int, array{when: \Carbon\CarbonInterface, what: string, where: ?string, matter: string}> */
    public function upcoming(): Collection
    {
        $matterIds = PortalAccess::matters(auth()->user())->pluck('matters.id');

        $hearings = Hearing::query()->whereIn('matter_id', $matterIds)
            ->whereIn('status', ['scheduled', 'adjourned'])->where('starts_at', '>=', now()->startOfDay())
            ->with(['court', 'matter'])->orderBy('starts_at')->limit(10)->get()
            ->map(fn (Hearing $h) => [
                'when' => $h->starts_at,
                'what' => Hearing::TYPES[$h->type] ?? 'Hearing',
                'where' => $h->court?->label(),
                'matter' => $h->matter?->reference,
            ]);

        $appointments = Appointment::query()->whereIn('matter_id', $matterIds)
            ->where('status', 'scheduled')->where('starts_at', '>=', now()->startOfDay())
            ->with('matter')->orderBy('starts_at')->limit(10)->get()
            ->map(fn (Appointment $a) => [
                'when' => $a->starts_at,
                'what' => Appointment::TYPES[$a->type] ?? 'Appointment',
                'where' => $a->location,
                'matter' => $a->matter?->reference,
            ]);

        return $hearings->concat($appointments)->sortBy('when')->take(10)->values();
    }

    /** @return array{count: int, balance: string, currency: string} */
    public function outstanding(): array
    {
        $invoices = PortalAccess::invoices(auth()->user())->whereIn('status', ['issued', 'partially_paid'])->get(['balance', 'currency']);
        $currency = $invoices->pluck('currency')->unique()->count() === 1 ? $invoices->first()->currency : 'UGX';

        return [
            'count' => $invoices->count(),
            'balance' => Money::add(...$invoices->pluck('balance')->all()),
            'currency' => $currency,
        ];
    }

    public function unreadMessages(): int
    {
        return ClientMessage::query()
            ->whereIn('client_id', PortalAccess::clientIds(auth()->user()))
            ->where('from_client', false)->whereNull('read_at')
            ->where(fn ($query) => $query->whereNull('matter_id')->orWhereIn('matter_id', PortalAccess::matters(auth()->user())->select('matters.id')))
            ->count();
    }

    public function openRequests(): Collection
    {
        return PortalAccess::documentRequests(auth()->user())->where('status', 'open')->with('matter')->orderBy('due_on')->get();
    }
}
