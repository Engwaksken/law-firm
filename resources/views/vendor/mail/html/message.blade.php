@php
    use App\Support\Settings;
    $firm = Settings::get('firm_name');
@endphp
<x-mail::layout>
    <x-slot:header>
        <x-mail::header :url="config('app.url')">{{ $firm }}</x-mail::header>
    </x-slot:header>

    {!! $slot !!}

    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>{!! $subcopy !!}</x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    <x-slot:footer>
        <x-mail::footer>© {{ date('Y') }} {{ $firm }}. All rights reserved.</x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
