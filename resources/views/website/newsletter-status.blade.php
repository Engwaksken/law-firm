<x-layouts.site :title="$title" noindex>
    <section class="section">
        <div class="container-site max-w-xl text-center">
            <h1 class="text-3xl font-semibold">{{ $title }}</h1>
            <p class="mt-4 text-ink/70">{{ $message }}</p>
            <a href="{{ route('home') }}" class="btn-primary mt-8">Return to the homepage</a>
        </div>
    </section>
</x-layouts.site>
