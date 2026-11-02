@php use App\Support\Settings; @endphp
<x-layouts.site title="Book a consultation" description="Request a consultation with one of our advocates." :breadcrumbs="['Book a consultation' => null]">
    <x-site.page-hero eyebrow="Consultations" title="Book a consultation"
        lead="Tell us what you need help with and when suits you. We will confirm the date and time with you." />

    <section class="section">
        <div class="container-site grid gap-12 lg:grid-cols-5">
            <div class="card p-6 sm:p-8 lg:col-span-3">
                <livewire:consultation-booking :practice-area="request('practice_area')" :advocate="request('advocate')" />
            </div>
            <aside class="space-y-6 lg:col-span-2">
                <div class="rounded-lg bg-brand p-6 text-white">
                    <h2 class="text-lg font-semibold text-white">What happens next</h2>
                    <ol class="mt-4 list-decimal space-y-3 pl-5 text-sm text-white/80">
                        <li>We review your request and check that we can act for you.</li>
                        <li>We contact you to confirm the date, time and format.</li>
                        <li>You meet the advocate best placed to help.</li>
                    </ol>
                </div>
                <div class="rounded-lg bg-brand-accent/10 p-5 text-sm text-ink/80" role="note">
                    <h2 class="font-semibold text-brand">Important</h2>
                    <p class="mt-1">{{ Settings::get('confidentiality_notice') }}</p>
                </div>
            </aside>
        </div>
    </section>
</x-layouts.site>
