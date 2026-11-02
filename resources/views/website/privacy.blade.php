@php use App\Support\Settings; $notice = Settings::get('privacy_notice'); @endphp
<x-layouts.site title="Privacy notice" :breadcrumbs="['Privacy notice' => null]">
    <x-site.page-hero title="Privacy notice" />
    <section class="section">
        <div class="container-site max-w-3xl">
            @if ($notice)
                <x-site.rich :html="$notice" />
            @else
                {{-- Shown until the firm publishes its approved notice from System → Website content. --}}
                <div class="prose-site">
                    <p>{{ Settings::get('firm_name') }} collects the personal information you give us through this website (for example, through the contact, consultation, newsletter and job application forms) only to respond to you and to provide the service you requested.</p>
                    <p>We do not sell your information. Access is limited to authorised staff, and we keep it only as long as necessary for these purposes or as required by law.</p>
                    <p>You may ask to access, correct or delete your personal information. To do so, or for any question about how we handle your data, please <a href="{{ route('contact') }}">contact us</a>.</p>
                </div>
            @endif
        </div>
    </section>
</x-layouts.site>
