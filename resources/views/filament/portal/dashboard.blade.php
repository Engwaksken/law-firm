<x-filament-panels::page>
    @php
        $outstanding = $this->outstanding();
        $requests = $this->openRequests();
        $unread = $this->unreadMessages();
        $upcoming = $this->upcoming();
        $matters = $this->matters();
    @endphp

    <div class="grid gap-4 sm:grid-cols-3">
        <x-filament::section>
            <p class="text-xs font-medium text-gray-500">Outstanding invoices</p>
            <p class="mt-1 text-2xl font-semibold">{{ \App\Support\Money::format($outstanding['balance'], $outstanding['currency']) }}</p>
            <p class="text-xs text-gray-500">{{ trans_choice(':count invoice|:count invoices', $outstanding['count']) }}</p>
            <a href="{{ \App\Filament\Portal\Resources\Invoices\InvoiceResource::getUrl() }}" class="mt-2 inline-block text-sm font-medium text-primary-600 hover:underline">View invoices</a>
        </x-filament::section>
        <x-filament::section>
            <p class="text-xs font-medium text-gray-500">Unread messages</p>
            <p class="mt-1 text-2xl font-semibold">{{ $unread }}</p>
            <a href="{{ \App\Filament\Portal\Resources\Messages\MessageResource::getUrl() }}" class="mt-2 inline-block text-sm font-medium text-primary-600 hover:underline">Open messages</a>
        </x-filament::section>
        <x-filament::section>
            <p class="text-xs font-medium text-gray-500">Documents requested from you</p>
            <p class="mt-1 text-2xl font-semibold">{{ $requests->count() }}</p>
            <a href="{{ \App\Filament\Portal\Resources\DocumentRequests\DocumentRequestResource::getUrl() }}" class="mt-2 inline-block text-sm font-medium text-primary-600 hover:underline">Upload documents</a>
        </x-filament::section>
    </div>

    <x-filament::section heading="Your matters">
        @if ($matters->isEmpty())
            <p class="text-sm text-gray-500">There are no matters to show yet.</p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($matters as $matter)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <div>
                            <a href="{{ \App\Filament\Portal\Resources\Matters\MatterResource::getUrl('view', ['record' => $matter]) }}" class="font-medium text-primary-600 hover:underline">{{ $matter->reference }} · {{ $matter->title }}</a>
                            @if ($matter->lead)<p class="text-xs text-gray-500">Advocate: {{ $matter->lead->name }}</p>@endif
                        </div>
                        <x-filament::badge>{{ $matter->statusLabel() }}</x-filament::badge>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>

    <x-filament::section heading="Upcoming dates">
        @if ($upcoming->isEmpty())
            <p class="text-sm text-gray-500">No upcoming hearings or appointments.</p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($upcoming as $item)
                    <li class="flex flex-wrap justify-between gap-2 py-3 text-sm">
                        <span><strong>{{ $item['when']->format('D j M Y, H:i') }}</strong> · {{ $item['what'] }}@if ($item['where']) · {{ $item['where'] }}@endif</span>
                        <span class="text-gray-500">{{ $item['matter'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>

    @if ($requests->isNotEmpty())
        <x-filament::section heading="Documents we need from you">
            <ul class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($requests as $request)
                    <li class="flex flex-wrap justify-between gap-2 py-3 text-sm">
                        <span><strong>{{ $request->title }}</strong> · {{ $request->matter?->reference }}</span>
                        <span class="text-gray-500">{{ $request->due_on ? 'Due '.$request->due_on->format('j M Y') : '' }}</span>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif
</x-filament-panels::page>
