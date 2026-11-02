@props(['name', 'label', 'id' => null, 'hint' => null, 'required' => false])
@php $id ??= 'f-'.str($name)->slug(); @endphp
{{-- Accessible field wrapper: label, hint and error are linked to the control via aria-describedby. --}}
<div {{ $attributes }}>
    <label for="{{ $id }}" class="form-label">
        {{ $label }}@if ($required)<span class="text-brand-secondary" aria-hidden="true"> *</span>@endif
    </label>
    {{ $slot }}
    @if ($hint)<p id="{{ $id }}-hint" class="form-hint">{{ $hint }}</p>@endif
    @error($name)<p id="{{ $id }}-error" class="form-error" role="alert">{{ $message }}</p>@enderror
</div>
