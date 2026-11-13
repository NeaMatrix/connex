<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Vite;
use NeaMatrix\Connex\Facades\Connex;

beforeEach(fn () => view()->addLocation(__DIR__.'/../../Fixtures/views'));

it('renders a project\'s own login view', function (): void {
    config(['connex.login.view' => 'my-login']);

    $this->get('/connex/login')->assertOk()->assertSee('Tawajah sign in')->assertSee('id="connex-submit"', false);
});

it('wraps the bundled pages in a project\'s own layout', function (): void {
    config(['connex.pages.layout' => 'my-layout']);

    $this->get('/connex/login')->assertOk()->assertSee('class="my-brand"', false)->assertSee('data-connex-login', false);
});

it('applies branding without publishing views', function (): void {
    config([
        'connex.pages.brand_name' => 'Tawajah',
        'connex.pages.brand_color' => '#123456',
        'connex.pages.logo_url' => 'https://cdn.example.com/logo.svg',
        'connex.pages.stylesheets' => ['https://cdn.example.com/connex-theme.css'],
    ]);

    $this->get('/connex/login')
        ->assertSee('--connex-brand: #123456', false)
        ->assertSee('https://cdn.example.com/logo.svg')
        ->assertSee('<link rel="stylesheet" href="https://cdn.example.com/connex-theme.css">', false)
        ->assertSee('Tawajah');
});

it('adds the app\'s CSP nonce to every inline tag', function (): void {
    Vite::useCspNonce('test-nonce');

    $html = $this->get('/connex/login')->assertOk()->getContent();

    expect(substr_count((string) $html, 'nonce="test-nonce"'))->toBe(4)
        ->and($html)->toContain('<meta name="csp-nonce" content="test-nonce">');
});

it('can drop the terms checkbox', function (): void {
    config(['connex.login.require_terms' => false]);
    Connex::fake();

    $this->get('/connex/login')->assertDontSee('name="accepted_terms"', false);
    $this->getJson('/connex/login/script');
    $this->postJson('/connex/login/otp', ['msisdn' => '0920920110'])->assertOk();
});

it('is never cached by browsers or proxies', function (): void {
    $this->get('/connex/login')->assertHeader('Cache-Control', 'no-store, private');
});
