@php($rtl = app()->getLocale() === 'ar')
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ config('connex.pages.brand_name') }}</title>
    <style>
        :root { --connex-brand: {{ config('connex.pages.brand_color', '#c8333b') }}; }
        {!! \NeaMatrix\Connex\Support\Assets::inline('css/connex.css') !!}
    </style>
</head>
<body>
    @include('connex::partials.nav')

    <main class="connex-main">
        @yield('content')
    </main>

    <script>{!! \NeaMatrix\Connex\Support\Assets::inline('js/connex.js') !!}</script>
    @stack('connex-scripts')
</body>
</html>
