@extends(config('connex.pages.layout', 'connex::layouts.page'))

@section('title', __('connex::account.title'))

@section('content')
@php
    $statusKey = $subscription?->status?->value ?? 'none';
@endphp
<section
    class="connex-card"
    data-connex-account
    data-activate-url="{{ route('connex.account.activation') }}"
    data-activate-confirm-url="{{ route('connex.account.activation.confirm') }}"
    data-cancel-url="{{ route('connex.account.cancellation') }}"
    data-cancel-confirm-url="{{ route('connex.account.cancellation.confirm') }}"
    data-plan-url="{{ route('connex.account.plan-change') }}"
    data-plan-confirm-url="{{ route('connex.account.plan-change.confirm') }}"
>
    <h1>{{ __('connex::account.title') }}</h1>

    @if ($notice)
        <p class="connex-notice">{{ $notice }}</p>
    @endif

    <p class="connex-error" role="alert" data-error hidden></p>
    <p class="connex-notice" data-success hidden></p>

    <dl class="connex-details">
        <dt>{{ __('connex::account.phone') }}</dt>
        <dd dir="ltr">{{ $msisdn ?? '—' }}</dd>
        <dt>{{ __('connex::account.status') }}</dt>
        <dd><span class="connex-badge connex-badge--{{ $statusKey }}">{{ __("connex::account.statuses.{$statusKey}") }}</span></dd>
        @if ($subscription?->plan_name)
            <dt>{{ __('connex::account.plan') }}</dt>
            <dd>{{ $subscription->plan_name }}</dd>
        @endif
        <dt>{{ __('connex::account.expires_at') }}</dt>
        <dd dir="ltr">{{ $subscription?->expires_at?->format('Y-m-d') ?? '—' }}</dd>
    </dl>

    @if ($subscription)
        @if ($subscription->status === \NeaMatrix\Connex\Enums\SubscriptionStatus::Active)
            <button type="button" class="connex-button connex-button--outline" data-action="cancel">{{ __('connex::account.cancel') }}</button>
        @else
            <button type="button" class="connex-button" data-action="activate">{{ __('connex::account.activate') }}</button>
        @endif
    @endif

    @if (count($plans) > 1)
        <button type="button" class="connex-link" data-toggle-plans>{{ __('connex::account.change_plan') }}</button>

        <form data-step="plans" novalidate hidden>
            <fieldset class="connex-plans">
                <legend>{{ __('connex::account.choose_plan') }}</legend>
                @foreach ($plans as $plan)
                    <label class="connex-plan">
                        <input type="radio" name="uuid" value="{{ $plan->uuid }}" @checked($plan->name === $subscription?->plan_name)>
                        <span class="connex-plan-name">{{ $plan->name }}</span>
                        @if ($plan->cost !== null)
                            <span class="connex-plan-cost" dir="ltr">{{ rtrim(rtrim(number_format($plan->cost, 3), '0'), '.') }} LYD</span>
                        @endif
                    </label>
                @endforeach
            </fieldset>
            <button type="submit" class="connex-button">{{ __('connex::account.change_plan') }}</button>
        </form>
    @endif

    <form data-step="otp" novalidate hidden>
        <label for="connex-account-otp">{{ __('connex::account.otp_label') }}</label>
        @include('connex::partials.otp-input', ['id' => 'connex-account-otp', 'length' => $otpLength])
        <button type="submit" class="connex-button">{{ __('connex::account.confirm') }}</button>
        <button type="button" class="connex-link" data-close>{{ __('connex::account.close') }}</button>
    </form>

    @if ($logoutUrl)
        <form method="POST" action="{{ $logoutUrl }}">
            @csrf
            <button type="submit" class="connex-link">{{ __('connex::account.logout') }}</button>
        </form>
    @endif
</section>
@endsection

@push('connex-scripts')
<script @if ($nonce = \NeaMatrix\Connex\Support\Assets::nonce()) nonce="{{ $nonce }}" @endif>{!! \NeaMatrix\Connex\Support\Assets::inline('js/connex-account.js') !!}</script>
@endpush
