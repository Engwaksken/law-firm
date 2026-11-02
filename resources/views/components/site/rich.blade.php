@props(['html' => null])
{{-- Renders CMS rich text. Always sanitised: staff-entered HTML is still untrusted (compromised accounts, pasted content). --}}
@if (filled($html))
    <div {{ $attributes->merge(['class' => 'prose-site']) }}>
        {!! str_contains($html, '<') ? \Illuminate\Support\Str::sanitizeHtml($html) : nl2br(e($html)) !!}
    </div>
@endif
