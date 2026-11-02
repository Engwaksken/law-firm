@php
    $title = $meta['meta_title'] ?: $meta['title'];
    $ogImage = ($meta['og_image_id'] ?? null) ? \App\Models\Media::find($meta['og_image_id'])?->url() : null;
    $crumbs = $isHome ? [] : [$meta['title'] => null];
@endphp
<x-layouts.site :title="$isHome ? null : $title" :description="$meta['meta_description'] ?: $meta['excerpt']"
    :image="$ogImage" :noindex="$preview || ($meta['noindex'] ?? false)" :breadcrumbs="$crumbs">

    @if ($preview)
        <div class="sticky top-20 z-40 bg-brand-accent text-brand" role="status">
            <div class="container-site flex flex-wrap items-center justify-between gap-2 py-2 text-sm font-semibold">
                <span>Preview: {{ $page->displayStatus() }}. Visitors {{ $page->isPublished() ? 'see the last published version' : 'cannot see this page' }}.</span>
                <a href="{{ \App\Filament\Resources\Pages\PageResource::getUrl('edit', ['record' => $page]) }}" class="underline">Back to editor</a>
            </div>
        </div>
    @endif

    @if (($meta['template'] ?? 'default') === 'default' && ! $isHome)
        <x-site.page-hero :title="$meta['title']" :lead="$meta['excerpt'] ?? null" />
    @endif

    @forelse ($blocks as $block)
        @includeIf('cms.blocks.'.$block['type'], ['data' => $block['data'], 'anchor' => $block['data']['anchor'] ?? null])
    @empty
        @if ($preview)
            <div class="container-site py-16 text-center text-ink/60">This page has no content blocks yet.</div>
        @endif
    @endforelse
</x-layouts.site>
