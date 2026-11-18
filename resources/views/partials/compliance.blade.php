{{--
    Connex Compliance Protect: the anti-fraud script looks for the price and the
    terms on the page (cptpl_price, cptpl_terms), next to the logo (cptpl_logo),
    service name (cptpl_service) and subscribe button (cptpl_subscribe).
--}}
@php($price = \NeaMatrix\Connex\Support\PageText::priceLines())
@if ($price !== [])
    <div class="connex-price pricepoint cptpl_price" id="cost" data-lang="cost">
        <p class="price_text">
            @foreach ($price as $line)
                {{ $line }}@if (! $loop->last)<br>@endif
            @endforeach
        </p>
    </div>
@endif
<p class="connex-terms price_text cptpl_terms">
    {{ __('connex::login.terms_prefix') }}
    @if (config('connex.pages.terms_url'))
        <a href="{{ config('connex.pages.terms_url') }}" target="_blank" rel="noopener">{{ __('connex::nav.terms') }}</a>
    @else
        {{ __('connex::nav.terms') }}
    @endif
    {{ __('connex::login.terms_suffix') }}
</p>
