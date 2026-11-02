@props(['title' => 'Speak to an advocate', 'text' => 'Tell us briefly about your situation and we will arrange a consultation with the right advocate.'])
<section class="bg-brand-secondary">
    <div class="container-site flex flex-col items-start gap-6 py-14 md:flex-row md:items-center md:justify-between">
        <div class="max-w-2xl">
            <h2 class="text-3xl font-semibold text-white">{{ $title }}</h2>
            <p class="mt-3 text-white/80">{{ $text }}</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('consultation') }}" class="btn-accent">Book a consultation</a>
            <a href="{{ route('contact') }}" class="btn-ghost-light">Contact us</a>
        </div>
    </div>
</section>
