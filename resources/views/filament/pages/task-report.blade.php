<x-filament-panels::page>
    <div class="flex flex-wrap items-end gap-3">
        <label class="text-sm">
            <span class="block text-xs font-medium text-gray-500">From</span>
            <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="from" /></x-filament::input.wrapper>
        </label>
        <label class="text-sm">
            <span class="block text-xs font-medium text-gray-500">Until</span>
            <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="until" /></x-filament::input.wrapper>
        </label>
    </div>

    @php $rows = $this->rows(); @endphp
    <x-filament::section>
        @if ($rows->isEmpty())
            <p class="text-sm text-gray-500">No assigned tasks to report.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <caption class="sr-only">Tasks per assignee</caption>
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs text-gray-500 dark:border-white/10">
                            <th scope="col" class="py-2 pe-4">Assignee</th>
                            <th scope="col" class="py-2 pe-4 text-right">Open</th>
                            <th scope="col" class="py-2 pe-4 text-right">Overdue</th>
                            <th scope="col" class="py-2 pe-4 text-right">Completed in period</th>
                            <th scope="col" class="py-2 text-right">Completed on time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($rows as $row)
                            <tr>
                                <th scope="row" class="py-2 pe-4 text-left font-medium">{{ $row['name'] }}</th>
                                <td class="py-2 pe-4 text-right">{{ $row['open'] }}</td>
                                <td class="py-2 pe-4 text-right {{ $row['overdue'] ? 'font-semibold text-danger-600' : '' }}">{{ $row['overdue'] }}</td>
                                <td class="py-2 pe-4 text-right">{{ $row['completed'] }}</td>
                                <td class="py-2 text-right">{{ $row['completed_on_time'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
