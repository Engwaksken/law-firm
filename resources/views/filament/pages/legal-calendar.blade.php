<x-filament-panels::page>
    @php
        $entries = $this->entries();
        $byDay = $entries->groupBy(fn ($e) => $e['at']->toDateString());
        $start = $this->start();
        $colors = [
            'hearing' => 'background:#fde7ef;color:#660033;',
            'deadline' => 'background:#fff4d6;color:#7a5600;',
            'appointment' => 'background:#e6eef8;color:#14283F;',
        ];
    @endphp

    <div class="flex flex-wrap items-end gap-3">
        <div class="flex items-center gap-1">
            <x-filament::button color="gray" size="sm" wire:click="previousMonth" icon="heroicon-m-chevron-left" aria-label="Previous month" />
            <x-filament::button color="gray" size="sm" wire:click="today">Today</x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="nextMonth" icon="heroicon-m-chevron-right" aria-label="Next month" />
        </div>
        <h2 class="text-xl font-semibold" aria-live="polite">{{ $start->format('F Y') }}</h2>

        <div class="ms-auto flex flex-wrap items-end gap-3">
            <label class="text-sm">
                <span class="block text-xs font-medium text-gray-500">Show</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="scope">
                        <option value="mine">My calendar</option>
                        <option value="all">Everything I can see</option>
                        @if ($this->canPickOthers())<option value="user">Another advocate…</option>@endif
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            @if ($scope === 'user' && $this->canPickOthers())
                <label class="text-sm">
                    <span class="block text-xs font-medium text-gray-500">Advocate</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="userId">
                            <option value="">Choose…</option>
                            @foreach ($this->staffOptions() as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
            @endif
            <label class="text-sm">
                <span class="block text-xs font-medium text-gray-500">Matter</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="matterId">
                        <option value="">All matters</option>
                        @foreach ($this->matterOptions() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label class="text-sm">
                <span class="block text-xs font-medium text-gray-500">Type</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="type">
                        <option value="">All types</option>
                        <option value="hearing">Hearings</option>
                        <option value="deadline">Deadlines</option>
                        <option value="appointment">Appointments</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            @if (Route::has('calendar.export'))
                <x-filament::button tag="a" color="gray" size="sm" icon="heroicon-m-arrow-down-tray" :href="route('calendar.export')">Export (.ics)</x-filament::button>
            @endif
        </div>
    </div>

    {{-- Month grid (hidden on small screens, where the agenda below is easier to read). --}}
    <div class="hidden overflow-hidden rounded-xl border border-gray-200 bg-white md:block dark:border-white/10 dark:bg-gray-900">
        <table class="w-full table-fixed text-sm">
            <caption class="sr-only">Calendar for {{ $start->format('F Y') }}</caption>
            <thead>
                <tr class="border-b border-gray-200 dark:border-white/10">
                    @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d)
                        <th scope="col" class="px-2 py-2 text-left text-xs font-semibold text-gray-500">{{ $d }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($this->weeks() as $week)
                    <tr class="border-b border-gray-100 last:border-0 dark:border-white/5">
                        @foreach ($week as $day)
                            @php $items = $byDay->get($day->toDateString(), collect()); @endphp
                            <td class="h-28 align-top border-e border-gray-100 p-1.5 last:border-0 dark:border-white/5 {{ $day->month !== $start->month ? 'bg-gray-50 text-gray-400 dark:bg-white/5' : '' }}">
                                <div class="mb-1 text-xs font-semibold {{ $day->isToday() ? 'inline-flex h-6 w-6 items-center justify-center rounded-full bg-primary-600 text-white' : '' }}">{{ $day->day }}</div>
                                <ul class="space-y-1">
                                    @foreach ($items->take(3) as $e)
                                        <li class="truncate rounded px-1.5 py-0.5 text-xs" style="{{ $colors[$e['kind']] }}" title="{{ $e['label'] }} · {{ $e['reference'] }} · {{ $e['title'] }}">
                                            {{ $e['at']->format('H:i') }} {{ $e['label'] }} {{ $e['reference'] }}
                                        </li>
                                    @endforeach
                                    @if ($items->count() > 3)<li class="text-xs text-gray-500">+{{ $items->count() - 3 }} more</li>@endif
                                </ul>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Agenda --}}
    <x-filament::section heading="Agenda">
        @if ($entries->isEmpty())
            <p class="text-sm text-gray-500">Nothing scheduled in this period.</p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($entries as $e)
                    <li class="flex flex-wrap items-baseline gap-x-4 gap-y-1 py-2.5 text-sm">
                        <time class="w-40 shrink-0 font-medium" datetime="{{ $e['at']->toIso8601String() }}">{{ $e['at']->format('D j M, H:i') }}</time>
                        <span class="rounded px-2 py-0.5 text-xs font-semibold" style="{{ $colors[$e['kind']] }}">{{ $e['label'] }}</span>
                        @if ($e['reference'])<span class="font-mono text-xs text-gray-500">{{ $e['reference'] }}</span>@endif
                        <span class="flex-1">{{ $e['title'] }}</span>
                        @if ($e['status'] && $e['status'] !== 'scheduled')<span class="text-xs text-gray-500">{{ ucfirst($e['status']) }}</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-panels::page>
