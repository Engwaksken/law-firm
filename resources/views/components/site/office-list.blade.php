@props(['offices'])
<div {{ $attributes->merge(['class' => 'grid gap-6 sm:grid-cols-2 lg:grid-cols-3']) }}>
    @foreach ($offices as $office)
        <div class="card p-6">
            <h3 class="text-lg font-semibold">
                {{ $office->name }}
                @if ($office->is_head_office)<span class="ml-2 rounded bg-brand-accent/20 px-2 py-0.5 align-middle text-xs font-semibold text-brand">Head office</span>@endif
            </h3>
            <address class="mt-3 space-y-1 text-sm text-ink/70 not-italic">
                @if ($office->address)<p>{{ $office->address }}</p>@endif
                <p>{{ collect([$office->city, $office->country])->filter()->implode(', ') }}</p>
                @if ($office->phone)<p><a href="tel:{{ preg_replace('/[^0-9+]/', '', $office->phone) }}" class="hover:text-brand">{{ $office->phone }}</a></p>@endif
                @if ($office->email)<p><a href="mailto:{{ $office->email }}" class="hover:text-brand">{{ $office->email }}</a></p>@endif
            </address>
        </div>
    @endforeach
</div>
