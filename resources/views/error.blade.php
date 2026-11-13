@extends(config('connex.pages.layout', 'connex::layouts.page'))

@section('title', __('connex::errors.invalid_link_title'))

@section('content')
<section class="connex-card">
    <h1>{{ __('connex::errors.invalid_link_title') }}</h1>
    <p class="connex-muted">{{ __('connex::errors.invalid_link') }}</p>
    @if (config('app.debug'))
        <p class="connex-error">{{ $reason }}</p>
    @endif
</section>
@endsection
