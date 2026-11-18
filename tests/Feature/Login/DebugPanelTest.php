<?php

declare(strict_types=1);

use NeaMatrix\Connex\Tests\Fixtures\ConnexHttp;

function startRealLogin(): void
{
    ConnexHttp::fake([
        'protected-script' => [['messageCode' => '00', 'success' => ['transaction_identify' => 'tx-1', 'dcbprotect' => str_repeat('x', 5000)]]],
        'login-confirm' => [['messageCode' => '01', 'failed' => ['message' => 'OTP incorrect', 'msisdn' => '218920374535']], 422],
        'login' => [['messageCode' => '05', 'failed' => ['message' => 'you have one OTP every 10 seconds', 'ft' => 999]], 429],
    ]);
}

it('shows nothing unless the debug panel is enabled', function (): void {
    startRealLogin();
    $this->get('/connex/login')->assertDontSee('data-connex-debug', false);

    $this->getJson('/connex/login/script')->assertOk()->assertJsonMissingPath('connex_debug');
});

it('returns the raw Connex exchanges with every secret hidden', function (): void {
    config(['connex.debug.enabled' => true]);
    startRealLogin();

    $this->get('/connex/login')->assertSee('data-connex-debug', false);

    $script = $this->getJson('/connex/login/script')->assertOk()->json('connex_debug');
    expect(array_column($script, 'endpoint'))->toBe(['auth-login', 'protected-script'])
        ->and($script[0]['request']['password'])->toBe('•••• (hidden)')
        ->and($script[0]['response']['data']['access_token'])->toBe('•••• (hidden)')
        ->and($script[1]['response']['success']['dcbprotect'])->toBe('<anti-fraud script, 5000 bytes>');

    $exchanges = $this->postJson('/connex/login/otp', ['msisdn' => '0920374535', 'accepted_terms' => true])
        ->assertStatus(429)
        ->json('connex_debug');
    $login = collect($exchanges)->firstWhere('endpoint', 'login');

    expect($login['url'])->toBe('https://connex.test/external-api/login')
        ->and($login['method'])->toBe('POST')
        ->and($login['duration_ms'])->toBeInt();
    expect($login['endpoint'])->toBe('login')
        ->and($login['status'])->toBe(429)
        ->and($login['request'])->toMatchArray(['msisdn' => '218920374535', 'transaction_identify' => 'tx-1'])
        ->and($login['response'])->toBe(['messageCode' => '05', 'failed' => ['message' => 'you have one OTP every 10 seconds', 'ft' => 999]]);
});

it('never exposes the OTP the subscriber typed', function (): void {
    config(['connex.debug.enabled' => true]);
    startRealLogin();
    session(['connex.login' => ['device' => 'web', 'msisdn' => '218920374535', 'transaction_id' => 'tx-1']]);

    $exchange = collect($this->postJson('/connex/login/otp/confirm', ['otp' => '4567'])->assertStatus(422)->json('connex_debug'))
        ->firstWhere('endpoint', 'login-confirm');

    expect($exchange['endpoint'])->toBe('login-confirm')
        ->and($exchange['request']['otp'])->toBe('•••• (hidden)')
        ->and(json_encode($exchange))->not->toContain('4567');
});
