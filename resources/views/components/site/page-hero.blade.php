@props(['eyebrow' => null, 'title', 'lead' => null])
<section class="relative overflow-hidden bg-brand text-white">
    <div aria-hidden="true" class="absolute inset-0 opacity-20" style="background: radial-gradient(circle at 85% 20%, var(--color-brand-secondary), transparent 55%);"></div>
    <div class="container-site relative py-16 sm:py-20">
        @if ($eyebrow)<p class="text-xs font-semibold tracking-[0.2em] text-brand-accent uppercase">{{ $eyebrow }}</p>@endif
        <h1 class="mt-3 max-w-3xl text-4xl font-semibold text-white sm:text-5xl">{{ $title }}</h1>
        @if ($lead)<p class="mt-5 max-w-2xl text-lg text-white/80">{{ $lead }}</p>@endif
        {{ $slot }}
    </div>
</section>
