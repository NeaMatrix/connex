<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NeaMatrix\Connex\Enums\SubscriptionStatus;
use NeaMatrix\Connex\Exceptions\ConnectionFailed;
use NeaMatrix\Connex\Facades\Connex;
use NeaMatrix\Connex\Models\ConnexSubscription;
use NeaMatrix\Connex\Tests\Fixtures\User;

function subscriber(string $state = 'active'): User
{
    $user = User::query()->create(['phone' => '218920920110', 'password' => 'secret']);
    ConnexSubscription::factory()->{$state}()->ownedBy($user)->create(['msisdn' => '218920920110']);

    return $user;
}

it('requires a signed-in user', function (): void {
    Route::get('/sign-in', fn () => 'sign in')->name('login');

    $this->get('/connex/account')->assertRedirect('/sign-in');
    $this->getJson('/connex/account/cancellation')->assertStatus(405);
    $this->postJson('/connex/account/cancellation')->assertUnauthorized();
});

it('shows the number, status and expiry, refreshed from Connex', function (): void {
    $connex = Connex::fake()->withSubscriber('0920920110', SubscriptionStatus::Cancelled);

    $this->actingAs(subscriber())->get('/connex/account?lang=en')
        ->assertOk()
        ->assertSee('0920920110')
        ->assertSee('Cancelled')
        ->assertSee('Activate subscription');

    $connex->assertCalled('subscriberDetails');
    expect(ConnexSubscription::query()->sole()->status)->toBe(SubscriptionStatus::Cancelled);
});

it('falls back to the stored state when Connex is down', function (): void {
    Connex::fake()->failWith('subscriberDetails', new ConnectionFailed('down'));

    $this->actingAs(subscriber())->get('/connex/account?lang=en')
        ->assertOk()
        ->assertSee('Cancel subscription');
});

it('cancels the subscription with an OTP', function (): void {
    $connex = Connex::fake()->withSubscriber('0920920110')->withOtp('5555');
    $this->actingAs(subscriber());

    $this->postJson('/connex/account/cancellation')->assertOk()->assertJson(['otp_sent' => true]);
    $this->postJson('/connex/account/cancellation/confirm', ['otp' => '1111'])->assertStatus(422);
    $this->postJson('/connex/account/cancellation/confirm', ['otp' => '5555'])->assertOk()->assertJson(['status' => 'cancel']);

    expect(ConnexSubscription::query()->sole()->status)->toBe(SubscriptionStatus::Cancelled);
    $connex->assertCalled('confirmCancellation', times: 2);
});

it('re-activates a cancelled subscription', function (): void {
    Connex::fake()->withSubscriber('0920920110', SubscriptionStatus::Cancelled);
    $this->actingAs(subscriber('cancelled'));

    $this->postJson('/connex/account/activation')->assertOk();
    $this->postJson('/connex/account/activation/confirm', ['otp' => '1234'])->assertOk()->assertJson(['status' => 'active']);

    expect(ConnexSubscription::query()->sole()->status)->toBe(SubscriptionStatus::Active);
});

it('reports when activation is not needed', function (): void {
    Connex::fake()->withSubscriber('0920920110');
    $this->actingAs(subscriber());

    $this->postJson('/connex/account/activation')->assertStatus(409)->assertJson(['error' => 'already_active']);
});

it('explains when the user has no subscription', function (): void {
    Connex::fake();
    $this->actingAs(User::query()->create(['phone' => '218910000000', 'password' => 'x']));

    $this->postJson('/connex/account/cancellation')->assertNotFound()->assertJson(['error' => 'not_subscribed']);
});
