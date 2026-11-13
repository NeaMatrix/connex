@php
    $links = array_filter([
        'home' => config('connex.pages.home_url'),
        'about' => config('connex.pages.about_url'),
        'terms' => config('connex.pages.terms_url'),
        'privacy' => config('connex.pages.privacy_url'),
        'contact' => config('connex.pages.contact_url'),
    ]);
    $otherLocale = app()->getLocale() === 'ar' ? 'en' : 'ar';
@endphp
<header class="connex-nav">
    <span class="connex-brand">{{ config('connex.pages.brand_name') }}</span>
    <nav>
        @foreach ($links as $key => $url)
            <a href="{{ $url }}">{{ __("connex::nav.{$key}") }}</a>
        @endforeach
        <a href="{{ request()->fullUrlWithQuery(['lang' => $otherLocale]) }}" hreflang="{{ $otherLocale }}">{{ __('connex::nav.language') }}</a>
    </nav>
</header>
