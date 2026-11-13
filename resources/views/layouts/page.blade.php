@php
    $rtl = app()->getLocale() === 'ar';
    $nonce = \NeaMatrix\Connex\Support\Assets::nonce();
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    @if ($nonce)
        <meta name="csp-nonce" content="{{ $nonce }}">
    @endif
    <title>@yield('title') · {{ config('connex.pages.brand_name') }}</title>
    <style @if ($nonce) nonce="{{ $nonce }}" @endif>
        :root { --connex-brand: {{ config('connex.pages.brand_color', '#c8333b') }}; }
        {!! \NeaMatrix\Connex\Support\Assets::inline('css/connex.css') !!}
    </style>
    @foreach ((array) config('connex.pages.stylesheets', []) as $stylesheet)
        <link rel="stylesheet" href="{{ $stylesheet }}">
    @endforeach
    @stack('connex-head')
</head>
<body>
    @include('connex::partials.nav')

    <main class="connex-main">
        @yield('content')
    </main>

    <script @if ($nonce) nonce="{{ $nonce }}" @endif>{!! \NeaMatrix\Connex\Support\Assets::inline('js/connex.js') !!}</script>
    @stack('connex-scripts')
</body>
</html>
