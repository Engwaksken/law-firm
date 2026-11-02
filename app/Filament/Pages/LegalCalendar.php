<?php

namespace App\Filament\Pages;

use App\Filament\Support\MatterOptions;
use App\Models\Appointment;
use App\Models\Deadline;
use App\Models\Hearing;
use App\Models\Matter;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Month grid + agenda of hearings, deadlines and appointments the VIEWER may
 * see. Choosing another advocate narrows to entries involving them, but never
 * reveals anything outside the viewer's own access.
 */
class LegalCalendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Legal Practice';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Calendar';

    protected static ?string $slug = 'calendar';

    protected string $view = 'filament.pages.legal-calendar';

    #[Url]
    public string $month = '';

    #[Url]
    public string $scope = 'mine';      // mine | all | user

    #[Url]
    public ?int $userId = null;

    #[Url]
    public ?int $matterId = null;

    public ?string $newFeedUrl = null;

    #[Url]
    public string $type = '';           // '' | hearing | deadline | appointment

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) $user && ($user->can('matters.view-assigned') || $user->can('matters.view-all'));
    }

    public function mount(): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = now()->format('Y-m');
        }
    }

    public function canPickOthers(): bool
    {
        return auth()->user()->can('matters.view-all') || auth()->user()->can('matters.assign');
    }

    public function previousMonth(): void { $this->month = $this->start()->subMonth()->format('Y-m'); }
    public function nextMonth(): void { $this->month = $this->start()->addMonth()->format('Y-m'); }
    public function today(): void { $this->month = now()->format('Y-m'); }

    public function rotateFeedToken(): void
    {
        $user = auth()->user();
        abort_unless($user && static::canAccess(), 403);

        $token = bin2hex(random_bytes(32));
        $user->forceFill(['calendar_feed_token_hash' => hash('sha256', $token)])->save();
        $this->newFeedUrl = route('calendar.feed', ['token' => $token]);
    }

    public function revokeFeedToken(): void
    {
        $user = auth()->user();
        abort_unless($user && static::canAccess(), 403);

        $user->forceFill(['calendar_feed_token_hash' => null])->save();
        $this->newFeedUrl = null;
    }

    public function start(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $this->month.'-01')->startOfDay();
    }

    /** @return array<int, string> */
    public function staffOptions(): array
    {
        return MatterOptions::teamOptions(null);
    }

    /** @return array<int, string> */
    public function matterOptions(): array
    {
        return MatterOptions::visible();
    }

    /**
     * @return Collection<int, array{kind: string, label: string, at: Carbon, reference: ?string, title: string, status: ?string, url: ?string}>
     */
    public function entries(): Collection
    {
        $viewer = auth()->user();
        $from = $this->start()->startOfWeek();
        $to = $this->start()->endOfMonth()->endOfWeek();

        $focus = match ($this->scope) {
            'mine' => $viewer,
            'user' => $this->canPickOthers() && $this->userId ? User::find($this->userId) : $viewer,
            default => null, // all visible
        };
        $focusMatterIds = $focus ? $this->matterIdsInvolving($focus) : null;

        $entries = collect();

        if ($this->type === '' || $this->type === 'hearing') {
            $entries = $entries->merge(Hearing::query()->visibleTo($viewer)->with('matter')
                ->whereBetween('starts_at', [$from, $to])
                ->when($this->matterId, fn ($query) => $query->where('matter_id', $this->matterId))
                ->when($focusMatterIds !== null, fn ($query) => $query->whereIn('matter_id', $focusMatterIds))
                ->get()->map(fn (Hearing $h) => [
                    'kind' => 'hearing', 'label' => Hearing::TYPES[$h->type] ?? 'Hearing', 'at' => $h->starts_at,
                    'reference' => $h->matter?->reference, 'title' => $h->matter?->title ?? '', 'status' => $h->status,
                    'url' => null,
                ]));
        }

        if ($this->type === '' || $this->type === 'deadline') {
            $entries = $entries->merge(Deadline::query()->visibleTo($viewer)->with('matter')
                ->whereBetween('due_at', [$from, $to])
                ->when($this->matterId, fn ($query) => $query->where('matter_id', $this->matterId))
                ->when($focus, fn ($query) => $query->where(fn ($query) => $query->where('responsible_user_id', $focus->id)
                    ->orWhere(fn ($query) => $query->whereNull('responsible_user_id')->whereIn('matter_id', Matter::query()->where('lead_user_id', $focus->id)->select('id')))))
                ->get()->map(fn (Deadline $d) => [
                    'kind' => 'deadline', 'label' => 'Deadline', 'at' => $d->due_at,
                    'reference' => $d->matter?->reference, 'title' => $d->title, 'status' => $d->completed_at ? 'completed' : ($d->isOverdue() ? 'overdue' : null),
                    'url' => null,
                ]));
        }

        if ($this->type === '' || $this->type === 'appointment') {
            $entries = $entries->merge(Appointment::query()->visibleTo($viewer)->with('matter')
                ->whereBetween('starts_at', [$from, $to])
                ->when($this->matterId, fn ($query) => $query->where('matter_id', $this->matterId))
                ->get()
                ->when($focus, fn (Collection $items) => $items->filter(fn (Appointment $a) => $a->involves($focus)))
                ->map(fn (Appointment $a) => [
                    'kind' => 'appointment', 'label' => Appointment::TYPES[$a->type] ?? 'Appointment', 'at' => $a->starts_at,
                    'reference' => $a->matter?->reference, 'title' => $a->title, 'status' => $a->status, 'url' => null,
                ]));
        }

        return $entries->sortBy(fn (array $e) => $e['at']->timestamp)->values();
    }

    /** @return array<int, array<int, Carbon>> weeks of days covering the month */
    public function weeks(): array
    {
        $day = $this->start()->startOfWeek();
        $end = $this->start()->endOfMonth()->endOfWeek();
        $weeks = [];
        while ($day <= $end) {
            $weeks[] = collect(range(0, 6))->map(fn ($i) => $day->copy()->addDays($i))->all();
            $day->addWeek();
        }

        return $weeks;
    }

    private function matterIdsInvolving(User $user): Collection
    {
        return Matter::query()->where('lead_user_id', $user->id)
            ->orWhereHas('assignments', fn ($query) => $query->where('user_id', $user->id))
            ->pluck('id');
    }
}
