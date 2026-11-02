<div>
    @if ($reference)
        <div class="rounded-lg bg-emerald-50 p-6" role="status" aria-live="polite">
            <h2 class="text-xl font-semibold text-emerald-900">Request received</h2>
            <p class="mt-2 text-emerald-800">Thank you. Your reference is <strong>{{ $reference }}</strong>. We have emailed you a confirmation and will contact you shortly to confirm the date and time.</p>
            <p class="mt-2 text-sm text-emerald-800">Please note this is a request, not yet a confirmed appointment.</p>
        </div>
    @else
        <form wire:submit="submit" class="relative space-y-6" novalidate>
            @include('livewire.partials.honeypot')

            <fieldset class="space-y-5">
                <legend class="font-display text-xl font-semibold text-brand">Your consultation</legend>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-site.field name="practiceAreaId" label="Practice area" id="f-area">
                        <select id="f-area" wire:model.live="practiceAreaId" class="form-input" @error('practiceAreaId') aria-invalid="true" aria-describedby="f-area-error" @enderror>
                            <option value="">Not sure / other</option>
                            @foreach ($this->practiceAreas as $area)
                                <option value="{{ $area->id }}">{{ $area->name }}</option>
                            @endforeach
                        </select>
                    </x-site.field>
                    <x-site.field name="advocateId" label="Preferred advocate" id="f-advocate"
                                  :hint="$this->advocates->isEmpty() ? 'We will assign a suitable advocate.' : null">
                        <select id="f-advocate" wire:model="advocateId" class="form-input" @disabled($this->advocates->isEmpty())
                                @error('advocateId') aria-invalid="true" aria-describedby="f-advocate-error" @enderror>
                            <option value="">No preference</option>
                            @foreach ($this->advocates as $advocate)
                                <option value="{{ $advocate->id }}">{{ $advocate->name }}@if ($advocate->title) ({{ $advocate->title }})@endif</option>
                            @endforeach
                        </select>
                    </x-site.field>
                </div>

                <div role="radiogroup" aria-labelledby="type-label">
                    <p id="type-label" class="form-label">Consultation type <span class="text-brand-secondary" aria-hidden="true">*</span></p>
                    <div class="grid gap-3 sm:grid-cols-3">
                        @foreach (\App\Models\Consultation::TYPES as $value => $label)
                            <label class="flex cursor-pointer items-center gap-3 rounded-md border border-black/15 bg-white p-3 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand/5">
                                <input type="radio" wire:model="consultationType" value="{{ $value }}" class="text-brand-secondary">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('consultationType')<p class="form-error" role="alert">{{ $message }}</p>@enderror
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-site.field name="preferredDate" label="Preferred date" id="f-date" required>
                        <input id="f-date" type="date" wire:model="preferredDate" min="{{ today()->toDateString() }}" class="form-input" required
                               @error('preferredDate') aria-invalid="true" aria-describedby="f-date-error" @enderror>
                    </x-site.field>
                    <x-site.field name="preferredTime" label="Preferred time" id="f-time">
                        <select id="f-time" wire:model="preferredTime" class="form-input">
                            <option value="">Any time</option>
                            <option value="morning">Morning</option>
                            <option value="afternoon">Afternoon</option>
                        </select>
                    </x-site.field>
                </div>
            </fieldset>

            <fieldset class="space-y-5">
                <legend class="font-display text-xl font-semibold text-brand">Your details</legend>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-site.field name="name" label="Full name" id="c-name" required class="sm:col-span-2">
                        <input id="c-name" type="text" wire:model="name" autocomplete="name" class="form-input" required @error('name') aria-invalid="true" aria-describedby="c-name-error" @enderror>
                    </x-site.field>
                    <x-site.field name="email" label="Email address" id="c-email" required>
                        <input id="c-email" type="email" wire:model="email" autocomplete="email" class="form-input" required @error('email') aria-invalid="true" aria-describedby="c-email-error" @enderror>
                    </x-site.field>
                    <x-site.field name="phone" label="Phone number" id="c-phone" required>
                        <input id="c-phone" type="tel" wire:model="phone" autocomplete="tel" class="form-input" required @error('phone') aria-invalid="true" aria-describedby="c-phone-error" @enderror>
                    </x-site.field>
                </div>
                <x-site.field name="message" label="Briefly, what do you need help with?" id="c-message"
                              hint="A short, general description is enough. Please don't share confidential details until we confirm we can act for you.">
                    <textarea id="c-message" wire:model="message" rows="4" class="form-input" aria-describedby="c-message-hint @error('message') c-message-error @enderror"></textarea>
                </x-site.field>
            </fieldset>

            <div>
                <label class="flex items-start gap-3 text-sm text-ink/80">
                    <input type="checkbox" wire:model="privacy" class="mt-0.5 h-4 w-4 rounded border-black/30 text-brand-secondary" @error('privacy') aria-invalid="true" aria-describedby="c-privacy-error" @enderror>
                    <span>I understand that this request does not create an advocate–client relationship, and I have read the <a href="{{ route('privacy') }}" class="font-semibold text-brand-secondary underline" target="_blank">privacy notice</a>.</span>
                </label>
                @error('privacy')<p id="c-privacy-error" class="form-error" role="alert">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submit">Request consultation</span>
                <span wire:loading wire:target="submit">Submitting…</span>
            </button>
        </form>
    @endif
</div>
