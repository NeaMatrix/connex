<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use NeaMatrix\Connex\Auth\Pkce;
use NeaMatrix\Connex\Enums\DeviceType;
use NeaMatrix\Connex\Facades\Connex;
use NeaMatrix\Connex\Tests\Fixtures\User;

const VERIFIER = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk-verifier';
const MOBILE_REDIRECT = 'myapp://auth/connex';

function mobileLoginUrl(array $overrides = []): string
{
    return '/connex/login?'.http_build_query($overrides + [
        'client' => 'mobile',
        'redirect_uri' => MOBILE_REDIRECT,
        'state' => 'xyz',
        'code_challenge' => Pkce::challengeFor(VERIFIER),
        'code_challenge_method' => 'S256',
        'device' => 'android',
    ]);
}

/**
 * @return array{redirect: string, code: string, state: string|null}
 */
function completeLogin(string $loginUrl): array
{
    test()->get($loginUrl)->assertOk();
    test()->getJson('/connex/login/script')->assertOk();
    test()->postJson('/connex/login/otp', ['msisdn' => '0920920110', 'accepted_terms' => true])->assertOk();
    $redirect = test()->postJson('/connex/login/otp/confirm', ['otp' => '1234'])->assertOk()->json('redirect');
    parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);

    return ['redirect' => $redirect, 'code' => $query['code'], 'state' => $query['state'] ?? null];
}

function exchange(string $code, array $overrides = []): TestResponse
{
    return test()->postJson('/connex/api/token', $overrides + [
        'client' => 'mobile',
        'code' => $code,
        'redirect_uri' => MOBILE_REDIRECT,
        'code_verifier' => VERIFIER,
    ]);
}

it('returns to the app with a code and its state, never a token', function (): void {
    $connex = Connex::fake();

    $result = completeLogin(mobileLoginUrl());

    expect($result['redirect'])->toStartWith(MOBILE_REDIRECT.'?code=')
        ->and($result['state'])->toBe('xyz')
        ->and(strlen($result['code']))->toBe(64);
    $this->assertGuest();
    $connex->assertCalled('confirmLogin', fn (array $call): bool => $call['device'] === DeviceType::Android);
});

it('exchanges the code for a working Sanctum token', function (): void {
    Connex::fake();
    Route::middleware(['api', 'auth:sanctum'])->get('/api/me', fn () => ['id' => auth()->id()]);

    $response = exchange(completeLogin(mobileLoginUrl())['code'])
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJson([
            'token_type' => 'Bearer',
            'is_new_subscriber' => true,
            'user' => ['id' => User::query()->sole()->id],
            'subscription' => ['msisdn' => '218920920110', 'status' => 'active', 'access' => 'granted', 'allows_access' => true],
        ]);

    expect(PersonalAccessToken::query()->sole()->name)->toBe('connex-android');
    $this->withToken($response->json('token'))->getJson('/api/me')->assertOk()->assertJson(['id' => User::query()->sole()->id]);
});

it('applies the configured token abilities and lifetime', function (): void {
    config(['connex.tokens.abilities' => ['learner'], 'connex.tokens.expires_in' => 60]);
    Connex::fake();

    $response = exchange(completeLogin(mobileLoginUrl())['code'])->assertOk();

    $token = PersonalAccessToken::query()->sole();
    expect($token->abilities)->toBe(['learner'])
        ->and($token->expires_at?->diffInMinutes(now(), true))->toBeGreaterThan(59)
        ->and($response->json('expires_at'))->not->toBeNull();
});

it('accepts each code once, for the client and redirect it was issued to', function (array $overrides): void {
    Connex::fake();
    $code = completeLogin(mobileLoginUrl())['code'];

    exchange($code, $overrides)->assertStatus(400)->assertJson(['error' => 'invalid_grant']);
    exchange($code)->assertStatus(400);
})->with([
    'wrong verifier' => [['code_verifier' => str_repeat('a', 43)]],
    'missing verifier' => [['code_verifier' => null]],
    'other client' => [['client' => 'web']],
    'other redirect' => [['redirect_uri' => 'myapp://evil']],
]);

it('rejects a reused or expired code', function (): void {
    Connex::fake();
    $code = completeLogin(mobileLoginUrl())['code'];

    exchange($code)->assertOk();
    exchange($code)->assertStatus(400);

    $second = completeLogin(mobileLoginUrl())['code'];
    $this->travel(121)->seconds();
    exchange($second)->assertStatus(400);
});

it('refuses bad login links without redirecting anywhere', function (array $overrides): void {
    $this->get(mobileLoginUrl($overrides))
        ->assertStatus(400)
        ->assertSee(__('connex::errors.invalid_link_title'));
})->with([
    'unknown client' => [['client' => 'nope']],
    'unregistered redirect' => [['redirect_uri' => 'https://evil.example.com']],
    'missing PKCE' => [['code_challenge' => null]],
    'plain PKCE' => [['code_challenge_method' => 'plain']],
    'bad response mode' => [['response_mode' => 'fragment']],
]);

it('lets a client without PKCE log in', function (): void {
    Connex::fake();
    $url = '/connex/login?'.http_build_query(['client' => 'web', 'redirect_uri' => 'https://app.example.com/auth/callback']);

    $code = completeLogin($url)['code'];

    exchange($code, ['client' => 'web', 'redirect_uri' => 'https://app.example.com/auth/callback', 'code_verifier' => null])->assertOk();
});

it('posts the code to the opener for popup logins', function (): void {
    Connex::fake();
    $this->get('/connex/login?'.http_build_query(['client' => 'web', 'redirect_uri' => 'https://app.example.com/auth/callback', 'state' => 's1', 'response_mode' => 'web_message']));
    $this->getJson('/connex/login/script');
    $this->postJson('/connex/login/otp', ['msisdn' => '0920920110', 'accepted_terms' => true]);

    $this->postJson('/connex/login/otp/confirm', ['otp' => '1234'])
        ->assertOk()
        ->assertJsonPath('web_message.target_origin', 'https://app.example.com')
        ->assertJsonPath('web_message.data.type', 'connex:login')
        ->assertJsonPath('web_message.data.state', 's1');
});

it('sends cancelled logins back to the client', function (): void {
    $this->get(mobileLoginUrl())->assertSee(route('connex.login.cancel'));

    $this->get('/connex/login/cancel')->assertRedirect(MOBILE_REDIRECT.'?error=access_denied&state=xyz');
});

it('can require every login to come from a client', function (): void {
    config(['connex.login.allow_session_login' => false]);

    $this->get('/connex/login')->assertStatus(400);
    $this->get(mobileLoginUrl())->assertOk();
});
