@php $form = $data['form'] ?? 'contact'; @endphp
<section @if ($anchor) id="{{ $anchor }}" @endif class="section">
    <div @class(['container-site', 'max-w-3xl' => $form !== 'newsletter', 'max-w-xl' => $form === 'newsletter'])>
        @if (filled($data['heading'] ?? null))<h2 class="text-3xl font-semibold">{{ $data['heading'] }}</h2>@endif
        @if (filled($data['intro'] ?? null))<p class="mt-3 text-ink/70">{{ $data['intro'] }}</p>@endif
        <div class="card mt-8 p-6 sm:p-8">
            @switch($form)
                @case('consultation') <livewire:consultation-booking /> @break
                @case('newsletter') <livewire:newsletter-signup /> @break
                @default <livewire:contact-form />
            @endswitch
        </div>
        @if ($form !== 'newsletter')
            <p class="mt-4 text-sm text-ink/60">{{ \App\Support\Settings::get('confidentiality_notice') }}</p>
        @endif
    </div>
</section>
