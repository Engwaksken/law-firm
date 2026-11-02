@php use App\Support\Settings; @endphp
<x-layouts.site title="Contact us" description="Get in touch with the firm." :breadcrumbs="['Contact' => null]">
    <x-site.page-hero eyebrow="Get in touch" title="Contact us" lead="Send us a message and a member of our team will respond as soon as possible." />

    <section class="section">
        <div class="container-site grid gap-12 lg:grid-cols-5">
            <div class="lg:col-span-3">
                <div class="card p-6 sm:p-8">
                    <h2 class="text-2xl font-semibold">Send a message</h2>
                    <p class="mt-2 text-sm text-ink/70">Looking for legal advice? <a href="{{ route('consultation') }}" class="font-semibold text-brand-secondary underline">Book a consultation</a> instead.</p>
                    <div class="mt-6"><livewire:contact-form /></div>
                </div>
            </div>
            <aside class="space-y-8 lg:col-span-2">
                <div>
                    <h2 class="text-xl font-semibold">Contact details</h2>
                    <dl class="mt-4 space-y-4 text-sm">
                        @if ($phone = Settings::get('contact_phone'))
                            <div><dt class="font-semibold text-brand">Phone</dt><dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="text-ink/70 hover:text-brand">{{ $phone }}</a></dd></div>
                        @endif
                        @if ($email = Settings::get('contact_email'))
                            <div><dt class="font-semibold text-brand">Email</dt><dd><a href="mailto:{{ $email }}" class="text-ink/70 hover:text-brand">{{ $email }}</a></dd></div>
                        @endif
                        @if ($address = Settings::get('contact_address'))
                            <div><dt class="font-semibold text-brand">Address</dt><dd class="whitespace-pre-line text-ink/70">{{ $address }}</dd></div>
                        @endif
                        <div><dt class="font-semibold text-brand">Office hours</dt><dd class="text-ink/70">{{ Settings::get('office_hours') }}</dd></div>
                    </dl>
                </div>
                <div class="rounded-lg bg-brand-accent/10 p-5 text-sm text-ink/80" role="note">
                    <h2 class="font-semibold text-brand">Confidentiality</h2>
                    <p class="mt-1">{{ Settings::get('confidentiality_notice') }}</p>
                </div>
            </aside>
        </div>
    </section>

    @if ($offices->isNotEmpty())
        <section id="offices" class="section bg-white" aria-labelledby="offices-heading">
            <div class="container-site">
                <h2 id="offices-heading" class="text-3xl font-semibold">Our offices</h2>
                <x-site.office-list :offices="$offices" class="mt-10" />
            </div>
        </section>
    @endif
</x-layouts.site>
