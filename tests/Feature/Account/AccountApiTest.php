<?php

declare(strict_types=1);

use NeaMatrix\Connex\Enums\DeviceType;
use NeaMatrix\Connex\Enums\SubscriptionStatus;
use NeaMatrix\Connex\Facades\Connex;
use NeaMatrix\Connex\Models\ConnexSubscription;
use NeaMatrix\Connex\Tests\Fixtures\User;

function apiSubscriber(): array
{
    $user = User::query()->create(['phone' => '218920920110', 'password' => 'secret']);
    ConnexSubscription::factory()->active()->ownedBy($user)->create(['msisdn' => '218920920110']);

    return [$user, $user->createToken('test')->plainTextToken];
}

it('requires a Sanctum token', function (): void {
    $this->getJson('/connex/api/subscription')->assertUnauthorized();
    $this->postJson('/connex/api/account-link')->assertUnauthorized();
});

it('returns the subscription, refreshed from Connex', function (): void {
    Connex::fake()->withSubscriber('0920920110', SubscriptionStatus::Cancelled);
    [, $token] = apiSubscriber();

    $this->withToken($token)->getJson('/connex/api/subscription')
        ->assertOk()
        ->assertJson(['subscription' => ['status' => 'cancel', 'access' => 'grace_period', 'allows_access' => true]]);
});

it('cancels with an OTP over the API', function (): void {
    Connex::fake()->withSubscriber('0920920110')->withOtp('5555');
    [, $token] = apiSubscriber();

    $this->withToken($token)->postJson('/connex/api/subscription/cancellation')->assertOk()->assertJson(['otp_sent' => true]);
    $this->withToken($token)->postJson('/connex/api/subscription/cancellation/confirm', ['otp' => '5555', 'device' => 'ios'])
        ->assertOk()
        ->assertJsonPath('subscription.status', 'cancel');

    Connex::getFacadeRoot()->assertCalled('confirmCancellation', fn (array $call): bool => $call['device'] === DeviceType::Ios);
});

it('opens the account page in a webview with a one-time ticket', function (): void {
    Connex::fake()->withSubscriber('0920920110');
    [$user, $token] = apiSubscriber();

    $url = $this->withToken($token)->postJson('/connex/api/account-link', ['device' => 'android', 'lang' => 'en'])
        ->assertOk()
        ->json('url');
    app('auth')->forgetGuards();

    $this->get($url)->assertRedirect(route('connex.account', ['lang' => 'en']));
    $this->assertAuthenticatedAs($user);
    $this->get('/connex/account?lang=en')->assertOk()->assertSee('Cancel subscription');

    app('auth')->guard('web')->logout();
    $this->get($url)->assertForbidden();
});
