<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Spam and abuse protection for public forms, without third-party CAPTCHA:
 *  - a honeypot field humans never see;
 *  - a minimum time between render and submit;
 *  - a per-IP rate limit per form.
 */
trait ProtectsPublicForms
{
    /** Honeypot. Hidden from people and screen readers; bots tend to fill it in. */
    public string $website = '';

    public int $renderedAt = 0;

    public function mountProtectsPublicForms(): void
    {
        $this->renderedAt = now()->timestamp;
    }

    /** @return bool false when the submission should be silently discarded (bot). */
    protected function passesSpamChecks(string $form, int $maxAttempts = 5, int $decaySeconds = 600): bool
    {
        if ($this->website !== '' || (now()->timestamp - $this->renderedAt) < $this->minimumSecondsToSubmit()) {
            return false;
        }

        $key = "public-form:{$form}:".request()->ip();
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
            throw ValidationException::withMessages([
                'form' => "Too many submissions. Please try again in {$minutes} minute(s) or contact us by phone.",
            ]);
        }
        RateLimiter::hit($key, $decaySeconds);

        return true;
    }

    protected function minimumSecondsToSubmit(): int
    {
        return app()->runningUnitTests() ? 0 : 3;
    }
}
