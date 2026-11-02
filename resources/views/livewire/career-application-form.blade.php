<div>
    @if ($reference)
        <div class="rounded-lg bg-emerald-50 p-5" role="status" aria-live="polite">
            <h3 class="font-semibold text-emerald-900">Application submitted</h3>
            <p class="mt-1 text-sm text-emerald-800">Thank you. Your reference is <strong>{{ $reference }}</strong>. We have emailed you a confirmation and will be in touch about next steps.</p>
        </div>
    @else
        <form wire:submit="submit" class="relative space-y-4" novalidate>
            @include('livewire.partials.honeypot')
            <x-site.field name="name" label="Full name" id="a-name" required>
                <input id="a-name" type="text" wire:model="name" autocomplete="name" class="form-input" required @error('name') aria-invalid="true" aria-describedby="a-name-error" @enderror>
            </x-site.field>
            <x-site.field name="email" label="Email address" id="a-email" required>
                <input id="a-email" type="email" wire:model="email" autocomplete="email" class="form-input" required @error('email') aria-invalid="true" aria-describedby="a-email-error" @enderror>
            </x-site.field>
            <x-site.field name="phone" label="Phone number" id="a-phone">
                <input id="a-phone" type="tel" wire:model="phone" autocomplete="tel" class="form-input">
            </x-site.field>
            <x-site.field name="cv" label="CV" id="a-cv" required hint="PDF or Word document, up to 5 MB.">
                <input id="a-cv" type="file" wire:model="cv" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                       class="form-input file:mr-3 file:rounded file:border-0 file:bg-brand file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white" required
                       aria-describedby="a-cv-hint @error('cv') a-cv-error @enderror" @error('cv') aria-invalid="true" @enderror>
                <p wire:loading wire:target="cv" class="form-hint" role="status">Uploading…</p>
            </x-site.field>
            <x-site.field name="coverLetter" label="Cover letter" id="a-cover">
                <textarea id="a-cover" wire:model="coverLetter" rows="5" class="form-input"></textarea>
            </x-site.field>
            <div>
                <label class="flex items-start gap-3 text-sm text-ink/80">
                    <input type="checkbox" wire:model="privacy" class="mt-0.5 h-4 w-4 rounded border-black/30 text-brand-secondary" @error('privacy') aria-invalid="true" aria-describedby="a-privacy-error" @enderror>
                    <span>I consent to the firm processing my application as described in the <a href="{{ route('privacy') }}" class="font-semibold text-brand-secondary underline" target="_blank">privacy notice</a>.</span>
                </label>
                @error('privacy')<p id="a-privacy-error" class="form-error" role="alert">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled" wire:target="submit,cv">
                <span wire:loading.remove wire:target="submit">Submit application</span>
                <span wire:loading wire:target="submit">Submitting…</span>
            </button>
        </form>
    @endif
</div>
