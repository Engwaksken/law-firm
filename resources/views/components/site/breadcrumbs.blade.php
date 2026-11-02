@props(['items' => []])
{{-- $items: [label => url|null]; the last item is the current page. --}}
<nav aria-label="Breadcrumb" class="border-b border-black/5 bg-white">
    <ol class="container-site flex flex-wrap items-center gap-2 py-3 text-sm text-ink/60">
        <li><a href="{{ route('home') }}" class="hover:text-brand">Home</a></li>
        @foreach ($items as $label => $url)
            <li aria-hidden="true">/</li>
            <li>
                @if ($loop->last || ! $url)
                    <span aria-current="page" class="font-medium text-brand">{{ $label }}</span>
                @else
                    <a href="{{ $url }}" class="hover:text-brand">{{ $label }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
