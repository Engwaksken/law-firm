{{-- Hidden from sighted users and assistive technology; only bots fill it in. --}}
<div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
    <label>Leave this field empty <input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label>
</div>
@error('form')<p class="rounded bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror
