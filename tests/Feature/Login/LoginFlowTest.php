<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use NeaMatrix\Connex\Enums\SubscriptionStatus;
use NeaMatrix\Connex\Facades\Connex;
use NeaMatrix\Connex\Login\Events\SubscriberLoggedIn;
use NeaMatrix\Connex\Models\ConnexSubscription;
use NeaMatrix\Connex\Tests\Fixtures\User;

function startLogin(string $query = ''): void
{
    test()->get('/connex/login'.$query)->assertOk();
    test()->getJson('/connex/login/script')->assertOk();
}

it('shows the login page with the anti-fraud target and the Connex-required links', function (): void {
    config(['connex.pages.terms_url' => 'https://example.com/terms', 'connex.pages.privacy_url' => 'https://example.com/privacy']);

    $this->get('/connex/login?lang=en')
        ->assertOk()
        ->assertSee('id="connex-submit"', false)
        ->assertSee('https://example.com/privacy')
        ->assertSee('العربية');

    $this->get('/connex/login?lang=ar')->assertSee('dir="rtl"', false)->assertSee('تسجيل');
});

it('signs a new subscriber in end to end', function (): void {
    $connex = Connex::fake()->withOtp('4321');
    Event::fake([SubscriberLoggedIn::class]);
    startLogin('?campaign_id=77');

    $this->postJson('/connex/login/otp', ['msisdn' => '092 092 0110', 'accepted_terms' => true])
        ->assertOk()
        ->assertJson(['msisdn' => '0920920110', 'resend_in' => 60]);

    $this->postJson('/connex/login/otp/confirm', ['otp' => '4321'])
        ->assertOk()
        ->assertJson(['redirect' => '/']);

    $user = User::query()->sole();
    expect($user->phone)->toBe('218920920110')
        ->and($user->connexSubscription?->status)->toBe(SubscriptionStatus::Active)
        ->and($user->hasConnexAccess())->toBeTrue();
    $this->assertAuthenticatedAs($user);

    $connex->assertOtpRequested('0920920110')
        ->assertCalled('confirmLogin', fn (array $call): bool => $call['campaignId'] === '77');
    Event::assertDispatched(SubscriberLoggedIn::class, fn (SubscriberLoggedIn $event): bool => $event->confirmation->isNewSubscriber());
});

it('signs an existing user in without changing their profile', function (): void {
    Connex::fake()->withSubscriber('0920920110');
    $user = User::query()->create(['name' => 'Ahmed', 'phone' => '218920920110', 'password' => 'secret']);
    startLogin();

    $this->postJson('/connex/login/otp', ['msisdn' => '0920920110', 'accepted_terms' => true])->assertOk();
    $this->postJson('/connex/login/otp/confirm', ['otp' => '1234'])->assertOk();

    expect(User::query()->sole()->only('name', 'phone'))->toBe(['name' => 'Ahmed', 'phone' => '218920920110']);
    $this->assertAuthenticatedAs($user);
});

it('stores national numbers when the app does', function (): void {
    config(['connex.user.format' => 'national']);
    Connex::fake();
    startLogin();

    $this->postJson('/connex/login/otp', ['msisdn' => '0920920110', 'accepted_terms' => true]);
    $this->postJson('/connex/login/otp/confirm', ['otp' => '1234'])->assertOk();

    expect(User::query()->sole()->phone)->toBe('0920920110');
});

it('explains every failure in the subscriber\'s language', function (array $otpRequest, int $status, string $error): void {
    Connex::fake()->fraudulent('0910000000');
    startLogin();

    $this->postJson('/connex/login/otp?lang=en', $otpRequest)->assertStatus($status)->assertJsonFragment($error === 'validation' ? [] : ['error' => $error]);
})->with([
    'bad number' => [['msisdn' => '12345', 'accepted_terms' => true], 422, 'validation'],
    'terms not accepted' => [['msisdn' => '0920920110'], 422, 'validation'],
    'fraud' => [['msisdn' => '0910000000', 'accepted_terms' => true], 403, 'fraud'],
]);

it('asks for the terms in the subscriber\'s language', function (): void {
    Connex::fake();
    startLogin('?lang=ar');

    $this->postJson('/connex/login/otp', ['msisdn' => '0920920110'])
        ->assertStatus(422)
        ->assertJsonPath('errors.accepted_terms.0', 'الرجاء الموافقة على الشروط والأحكام للمتابعة.');
});

it('rejects a wrong OTP and keeps the visitor signed out', function (): void {
    Connex::fake()->withOtp('4321');
    startLogin();
    $this->postJson('/connex/login/otp', ['msisdn' => '0920920110', 'accepted_terms' => true]);

    $this->postJson('/connex/login/otp/confirm', ['otp' => '1111'])
        ->assertStatus(422)
        ->assertJson(['error' => 'invalid_otp']);

    $this->assertGuest();
    expect(ConnexSubscription::query()->count())->toBe(0);
});

it('requires the steps in order', function (): void {
    $connex = Connex::fake();
    $this->get('/connex/login');

    $this->postJson('/connex/login/otp', ['msisdn' => '0920920110', 'accepted_terms' => true])->assertStatus(419);
    $this->postJson('/connex/login/otp/confirm', ['otp' => '1234'])->assertStatus(419);

    $connex->assertNotCalled('login')->assertNotCalled('confirmLogin');
});

it('enforces the resend cooldown', function (): void {
    $connex = Connex::fake();
    startLogin();

    $this->postJson('/connex/login/otp', ['msisdn' => '0920920110', 'accepted_terms' => true])->assertOk();
    $this->postJson('/connex/login/otp', ['msisdn' => '0920920110', 'accepted_terms' => true])
        ->assertStatus(429)
        ->assertJsonPath('resend_in', 60);

    $this->travel(61)->seconds();
    $this->postJson('/connex/login/otp', ['msisdn' => '0920920110', 'accepted_terms' => true])->assertOk();

    $connex->assertCalled('login', times: 2);
});

it('rate limits OTP guessing per IP', function (): void {
    config(['connex.login.throttle.confirm' => 3]);
    Connex::fake()->withOtp('4321');
    startLogin();
    $this->postJson('/connex/login/otp', ['msisdn' => '0920920110', 'accepted_terms' => true]);

    foreach (range(1, 3) as $attempt) {
        $this->postJson('/connex/login/otp/confirm', ['otp' => '000'.$attempt])->assertStatus(422);
    }

    $this->postJson('/connex/login/otp/confirm', ['otp' => '4321'])->assertStatus(429);
});
