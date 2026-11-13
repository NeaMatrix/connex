<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use NeaMatrix\Connex\Enums\SubscriptionStatus;
use NeaMatrix\Connex\Facades\Connex;
use NeaMatrix\Connex\Models\ConnexSubscription;

beforeEach(fn () => config(['connex.login.require_access' => true]));

function confirmAppLogin(): TestResponse
{
    test()->get('/connex/login?'.http_build_query(['client' => 'web', 'redirect_uri' => 'https://app.example.com/auth/callback', 'state' => 's']));
    test()->getJson('/connex/login/script');
    test()->postJson('/connex/login/otp', ['msisdn' => '0920920110', 'accepted_terms' => true])->assertOk();

    return test()->postJson('/connex/login/otp/confirm', ['otp' => '1234'])->assertOk();
}

it('asks a lapsed subscriber to activate before handing the login back', function (): void {
    $connex = Connex::fake()->withSubscriber('0920920110', SubscriptionStatus::Cancelled, CarbonImmutable::now()->subDay());

    confirmAppLogin()
        ->assertJson(['step' => 'activation', 'access' => 'expired'])
        ->assertJsonMissingPath('redirect');

    $this->postJson('/connex/login/activation')->assertOk()->assertJson(['otp_sent' => true]);
    $this->postJson('/connex/login/activation/confirm', ['otp' => '0000'])->assertStatus(422)->assertJson(['error' => 'invalid_otp']);

    $redirect = $this->postJson('/connex/login/activation/confirm', ['otp' => '1234'])->assertOk()->json('redirect');

    expect($redirect)->toStartWith('https://app.example.com/auth/callback?code=')
        ->and(ConnexSubscription::query()->sole()->status)->toBe(SubscriptionStatus::Active);
    $connex->assertCalled('confirmActivation', times: 2);
});

it('lets a subscriber whose renewal failed check again after topping up', function (): void {
    $connex = Connex::fake()->withSubscriber('0920920110', SubscriptionStatus::Active, CarbonImmutable::now()->subHour());

    confirmAppLogin()->assertJson(['step' => 'activation', 'access' => 'insufficient_balance']);
    $this->postJson('/connex/login/access/check')->assertJson(['step' => 'activation']);

    $connex->withSubscriber('0920920110', SubscriptionStatus::Active, CarbonImmutable::now()->addDay());

    expect($this->postJson('/connex/login/access/check')->assertOk()->json('redirect'))->toContain('code=');
});

it('hands back a login straight away when the subscription is active', function (): void {
    Connex::fake()->withSubscriber('0920920110');

    confirmAppLogin()->assertJsonPath('redirect', fn (string $url): bool => str_contains($url, 'code='));
});

it('needs a confirmed login before activating', function (): void {
    Connex::fake();
    $this->get('/connex/login');

    $this->postJson('/connex/login/activation')->assertStatus(419);
    $this->postJson('/connex/login/access/check')->assertStatus(419);
});

it('hands back lapsed logins too when access is not required', function (): void {
    config(['connex.login.require_access' => false]);
    Connex::fake()->withSubscriber('0920920110', SubscriptionStatus::Cancelled, CarbonImmutable::now()->subDay());

    confirmAppLogin()->assertJsonPath('redirect', fn (string $url): bool => str_contains($url, 'code='));
});
