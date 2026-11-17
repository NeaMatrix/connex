<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use NeaMatrix\Connex\Enums\DeviceType;
use NeaMatrix\Connex\Facades\Connex;
use NeaMatrix\Connex\Models\ConnexSubscription;
use NeaMatrix\Connex\Tests\Fixtures\ConnexHttp;
use NeaMatrix\Connex\Tests\Fixtures\User;

function planSubscriber(): string
{
    $user = User::query()->create(['phone' => '218920920110', 'password' => 'secret']);
    ConnexSubscription::factory()->active()->ownedBy($user)->create(['msisdn' => '218920920110', 'plan_name' => 'Daily']);

    return $user->createToken('test')->plainTextToken;
}

it('lists the packages the subscriber can choose', function (): void {
    Connex::fake();

    $this->withToken(planSubscriber())->getJson('/connex/api/subscription/plans')
        ->assertOk()
        ->assertJsonCount(2, 'plans')
        ->assertJsonPath('plans.1', [
            'uuid' => 'fake-weekly', 'name' => 'Weekly', 'period_hours' => 168,
            'free_period_hours' => 72, 'product_id' => 2, 'cost' => 1.5,
        ]);
});

it('changes the package after the OTP is confirmed', function (): void {
    $connex = Connex::fake()->withSubscriber('0920920110')->withOtp('5555');
    $token = planSubscriber();

    $this->withToken($token)->postJson('/connex/api/subscription/plan-change', ['uuid' => 'fake-weekly'])
        ->assertOk()
        ->assertJson(['otp_sent' => true]);
    $this->withToken($token)->postJson('/connex/api/subscription/plan-change/confirm', ['uuid' => 'fake-weekly', 'otp' => '1111'])
        ->assertStatus(422)
        ->assertJson(['error' => 'invalid_otp']);
    $this->withToken($token)->postJson('/connex/api/subscription/plan-change/confirm', ['uuid' => 'fake-weekly', 'otp' => '5555', 'device' => 'ios'])
        ->assertOk()
        ->assertJsonPath('subscription.status', 'active');

    $connex->assertCalled('requestPlanChange', fn (array $call): bool => $call['planUuid'] === 'fake-weekly')
        ->assertCalled('confirmPlanChange', times: 2);
});

it('validates the plan change payload', function (string $uri, array $body, array $errors): void {
    Connex::fake();

    $this->withToken(planSubscriber())->postJson($uri, $body)->assertUnprocessable()->assertJsonValidationErrors($errors);
})->with([
    'no plan' => ['/connex/api/subscription/plan-change', [], ['uuid']],
    'no otp' => ['/connex/api/subscription/plan-change/confirm', ['uuid' => 'fake-weekly'], ['otp']],
]);

it('sends Connex the upgrade request it documents', function (): void {
    ConnexHttp::fake([
        'subscriber-details' => [['messageCode' => '00', 'success' => ['msisdn' => '218920920110', 'subscriber' => 'exist', 'details' => ['status' => 'active', 'subscription_name' => 'Weekly']]]],
        'upgrade-confirm' => [['messageCode' => '00', 'success' => ['message' => 'subscription upgraded', 'msisdn' => '218920920110']]],
    ]);

    $this->withToken(planSubscriber())
        ->postJson('/connex/api/subscription/plan-change/confirm', ['uuid' => 'c6171b90', 'otp' => '4567', 'device' => 'android'])
        ->assertOk()
        ->assertJsonPath('subscription.plan', 'Weekly');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/upgrade-confirm')
        && $request['msisdn'] === '218920920110' && $request['uuid'] === 'c6171b90'
        && $request['otp'] === '4567' && $request['device_type'] === DeviceType::Android->value);
});

it('lists the subscriber\'s charges', function (): void {
    ConnexHttp::fake(['subscriber-transactions' => [['messageCode' => '00', 'success' => ['msisdn' => '218920920110', 'list' => [
        ['invoice_no' => '234566667', 'connex_transaction_id' => '1677149359', 'amount' => 0.25, 'status' => 'success', 'transactions_date' => '2023-02-23 12:49:19'],
    ]]]]]);

    $this->withToken(planSubscriber())->getJson('/connex/api/subscription/transactions')
        ->assertOk()
        ->assertJsonPath('transactions.0', [
            'transaction_id' => '1677149359', 'invoice_no' => '234566667', 'amount' => 0.25,
            'status' => 'success', 'charged_at' => '2023-02-23T10:49:19+00:00',
        ]);
});

it('offers the package switch on the account page', function (): void {
    Connex::fake()->withSubscriber('0920920110');
    $user = User::query()->create(['phone' => '218920920110', 'password' => 'secret']);
    ConnexSubscription::factory()->active()->ownedBy($user)->create(['msisdn' => '218920920110']);

    $this->actingAs($user)->get('/connex/account?lang=en')
        ->assertOk()
        ->assertSee('Change package')
        ->assertSee('value="fake-weekly"', false)
        ->assertSee('1.5 LYD');
});
