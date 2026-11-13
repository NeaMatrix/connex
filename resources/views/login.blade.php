@extends(config('connex.pages.layout', 'connex::layouts.page'))

@section('title', __('connex::login.title'))

@section('content')
<section
    class="connex-card"
    data-connex-login
    data-script-url="{{ route('connex.login.script') }}"
    data-otp-url="{{ route('connex.login.otp') }}"
    data-confirm-url="{{ route('connex.login.confirm') }}"
    data-gateway-timeout="{{ $gatewayTimeout }}"
    data-resend-template="{{ __('connex::login.resend_in', ['seconds' => ':seconds']) }}"
>
    <div class="connex-loader" data-loader>
        <span class="connex-spinner" aria-hidden="true"></span>
        <p>{{ __('connex::login.loading') }}</p>
    </div>

    <p class="connex-error" role="alert" data-error hidden></p>
    <button type="button" class="connex-link" data-retry hidden>{{ __('connex::login.retry') }}</button>

    <form data-step="phone" novalidate>
        <label for="connex-msisdn">{{ __('connex::login.phone_label') }}</label>
        <input id="connex-msisdn" name="msisdn" class="connex-input" type="tel" inputmode="tel"
               autocomplete="tel-national" dir="ltr" placeholder="{{ __('connex::login.phone_placeholder') }}" required>

        @if ($requireTerms)
            <label class="connex-check">
                <input type="checkbox" name="accepted_terms" value="1" required>
                <span>
                    @if (config('connex.pages.terms_url'))
                        <a href="{{ config('connex.pages.terms_url') }}" target="_blank" rel="noopener">{{ __('connex::login.accept_terms') }}</a>
                    @else
                        {{ __('connex::login.accept_terms') }}
                    @endif
                </span>
            </label>
        @endif

        <button id="{{ $targetElement }}" type="submit" class="connex-button" disabled>{{ __('connex::login.submit') }}</button>

        @if ($canCancel)
            <a href="{{ route('connex.login.cancel') }}" class="connex-link">{{ __('connex::login.cancel') }}</a>
        @endif
    </form>

    <form data-step="otp" novalidate hidden>
        <label for="connex-otp" data-otp-label
               data-template="{{ __('connex::login.otp_label', ['msisdn' => ':msisdn']) }}"
               data-sms-template="{{ __('connex::login.otp_sms_reply', ['msisdn' => ':msisdn']) }}"></label>
        @include('connex::partials.otp-input', ['id' => 'connex-otp', 'length' => $otpLength])

        <button type="submit" class="connex-button">{{ __('connex::login.confirm') }}</button>

        <p class="connex-muted">
            {{ __('connex::login.no_code') }}
            <button type="button" class="connex-link" data-resend>{{ __('connex::login.resend') }}</button>
        </p>
        <button type="button" class="connex-link" data-change-number>{{ __('connex::login.change_number') }}</button>
    </form>
</section>
@endsection

@push('connex-scripts')
<script @if ($nonce = \NeaMatrix\Connex\Support\Assets::nonce()) nonce="{{ $nonce }}" @endif>{!! \NeaMatrix\Connex\Support\Assets::inline('js/connex-login.js') !!}</script>
@endpush
