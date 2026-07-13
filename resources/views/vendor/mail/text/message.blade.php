@component('mail::layout')
    {{-- Header --}}
    @slot('header')
        {{ $brandName ?? config('app.name') }}
    @endslot

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        @slot('subcopy')
            {{ $subcopy }}
        @endslot
    @endisset

    {{-- Footer --}}
    @slot('footer')
        © {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
    @endslot
@endcomponent
