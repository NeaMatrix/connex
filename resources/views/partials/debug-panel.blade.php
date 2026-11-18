{{--
    Connex debug panel (config "connex.debug.enabled"). Beside the page on wide
    screens: every action, the Connex endpoint it called, and Connex's raw reply.
--}}
@if (config('connex.debug.enabled'))
@php($nonce = \NeaMatrix\Connex\Support\Assets::nonce())
<aside class="cxd" data-connex-debug dir="ltr" aria-label="Connex debug log" data-base-url="{{ config('connex.base_url') }}">
    <header class="cxd-top">
        <div>
            <strong>Connex debug log</strong>
            <span>Connex server: <code>{{ config('connex.base_url') ?: '— not set —' }}</code>{{ config('connex.fake.enabled') && ! app()->isProduction() ? ' · FAKE MODE' : '' }}</span>
        </div>
        <button type="button" data-debug-clear>Clear</button>
    </header>
    <p class="cxd-empty" data-debug-empty>Waiting for the first request… Every call to Connex appears here with its exact reply. OTP, password and token are hidden.</p>
    <ol class="cxd-list" data-debug-entries></ol>
</aside>
<style @if ($nonce) nonce="{{ $nonce }}" @endif>{!! \NeaMatrix\Connex\Support\Assets::inline('css/connex-debug.css') !!}</style>
<script @if ($nonce) nonce="{{ $nonce }}" @endif>{!! \NeaMatrix\Connex\Support\Assets::inline('js/connex-debug.js') !!}</script>
@endif
