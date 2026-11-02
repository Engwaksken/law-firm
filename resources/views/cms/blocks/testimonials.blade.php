@php $testimonials = \App\Models\Testimonial::query()->publishable()->take(max(1, min(6, (int) ($data['limit'] ?? 3))))->get(); @endphp
@if ($testimonials->isNotEmpty())
    <section @if ($anchor) id="{{ $anchor }}" @endif class="section">
        <div class="container-site">
            @if (filled($data['heading'] ?? null))<h2 class="text-center text-3xl font-semibold">{{ $data['heading'] }}</h2>@endif
            <div @class(['mt-10 grid gap-6', 'lg:grid-cols-2' => $testimonials->count() === 2, 'lg:grid-cols-3' => $testimonials->count() >= 3])>
                @foreach ($testimonials as $testimonial)
                    <figure class="card p-8">
                        <blockquote class="text-lg leading-relaxed text-ink/80">“{{ $testimonial->quote }}”</blockquote>
                        <figcaption class="mt-6 text-sm">
                            <span class="font-semibold text-brand">{{ $testimonial->author_name }}</span>
                            @if ($testimonial->author_title)<span class="text-ink/60">, {{ $testimonial->author_title }}</span>@endif
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>
@endif
