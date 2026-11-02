@php $dark = $variant === 'dark'; $id = 'nl-'.$this->getId(); @endphp
<div>
    @if ($done)
        <p role="status" aria-live="polite" class="text-sm {{ $dark ? 'text-white' : 'text-emerald-800' }}">
            Thank you. Please check your inbox and click the link to confirm your subscription.
        </p>
    @else
        <form wire:submit="subscribe" class="relative" novalidate>
            @include('livewire.partials.honeypot')
            <label for="{{ $id }}" class="{{ $dark ? 'mb-2 block text-sm font-semibold text-white' : 'form-label' }}">Subscribe to our newsletter</label>
            <div class="flex gap-2">
                <input id="{{ $id }}" type="email" wire:model="email" autocomplete="email" placeholder="you@example.com" required
                       class="form-input {{ $dark ? 'border-white/20 bg-white/10 text-white placeholder:text-white/50' : '' }}"
                       @error('email') aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror>
                <button type="submit" class="{{ $dark ? 'btn-accent' : 'btn-primary' }} shrink-0" wire:loading.attr="disabled">Subscribe</button>
            </div>
            @error('email')<p id="{{ $id }}-error" class="form-error {{ $dark ? 'text-red-300' : '' }}" role="alert">{{ $message }}</p>@enderror
        </form>
    @endif
</div>
