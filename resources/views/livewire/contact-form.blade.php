<div>
    @if ($sent)
        <div class="rounded-lg bg-emerald-50 p-6" role="status" aria-live="polite">
            <h3 class="font-semibold text-emerald-900">Thank you. Your message has been sent.</h3>
            <p class="mt-1 text-sm text-emerald-800">We have emailed you a confirmation and will reply as soon as possible.</p>
            <button type="button" wire:click="$set('sent', false)" class="mt-4 text-sm font-semibold text-emerald-900 underline">Send another message</button>
        </div>
    @else
        <form wire:submit="submit" class="relative space-y-5" novalidate>
            @include('livewire.partials.honeypot')
            <div class="grid gap-5 sm:grid-cols-2">
                <x-site.field name="name" label="Full name" required>
                    <input id="f-name" type="text" wire:model="name" autocomplete="name" class="form-input" required
                           @error('name') aria-invalid="true" aria-describedby="f-name-error" @enderror>
                </x-site.field>
                <x-site.field name="email" label="Email address" required>
                    <input id="f-email" type="email" wire:model="email" autocomplete="email" class="form-input" required
                           @error('email') aria-invalid="true" aria-describedby="f-email-error" @enderror>
                </x-site.field>
                <x-site.field name="phone" label="Phone number">
                    <input id="f-phone" type="tel" wire:model="phone" autocomplete="tel" class="form-input"
                           @error('phone') aria-invalid="true" aria-describedby="f-phone-error" @enderror>
                </x-site.field>
                <x-site.field name="subject" label="Subject">
                    <input id="f-subject" type="text" wire:model="subject" class="form-input"
                           @error('subject') aria-invalid="true" aria-describedby="f-subject-error" @enderror>
                </x-site.field>
            </div>
            <x-site.field name="message" label="Message" required hint="Please don't include confidential details until we have confirmed we can act for you.">
                <textarea id="f-message" wire:model="message" rows="6" class="form-input" required
                          aria-describedby="f-message-hint @error('message') f-message-error @enderror" @error('message') aria-invalid="true" @enderror></textarea>
            </x-site.field>
            <div>
                <label class="flex items-start gap-3 text-sm text-ink/80">
                    <input type="checkbox" wire:model="privacy" class="mt-0.5 h-4 w-4 rounded border-black/30 text-brand-secondary"
                           @error('privacy') aria-invalid="true" aria-describedby="f-privacy-error" @enderror>
                    <span>I understand that sending this message does not create an advocate–client relationship, and I have read the <a href="{{ route('privacy') }}" class="font-semibold text-brand-secondary underline" target="_blank">privacy notice</a>.</span>
                </label>
                @error('privacy')<p id="f-privacy-error" class="form-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submit">Send message</span>
                <span wire:loading wire:target="submit">Sending…</span>
            </button>
        </form>
    @endif
</div>
