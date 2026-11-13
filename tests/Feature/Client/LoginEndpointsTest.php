<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use NeaMatrix\Connex\Client\ClientOptions;
use NeaMatrix\Connex\Contracts\ConnexClient;
use NeaMatrix\Connex\Enums\DeviceType;
use NeaMatrix\Connex\Enums\SubscriberState;
use NeaMatrix\Connex\Enums\SubscriptionStatus;
use NeaMatrix\Connex\Exceptions\FraudDetected;
use NeaMatrix\Connex\Exceptions\InsufficientBalance;
use NeaMatrix\Connex\Exceptions\InvalidOtp;
use NeaMatrix\Connex\Exceptions\TooManyRequests;
use NeaMatrix\Connex\Tests\Fixtures\ConnexHttp;

it('fetches the anti-fraud script', function (): void {
    ConnexHttp::fake(['protected-script' => [['messageCode' => '00', 'success' => ['message' => 'DCB Protected script', 'transaction_identify' => 'c136d6c0', 'dcbprotect' => 'console.log(1)']]]]);

    $script = app(ConnexClient::class)->protectedScript('#connex-submit');

    expect($script->transactionId)->toBe('c136d6c0')->and($script->script)->toBe('console.log(1)');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && $request['targeted_element'] === '#connex-submit');
});

it('sends the login request Connex documents', function (): void {
    config(['connex.script_owner' => 'my-evina-account']);
    app()->forgetInstance(ClientOptions::class);
    ConnexHttp::fake(['login' => [['messageCode' => '00', 'success' => ['message' => 'OTP send success', 'subscriber' => 'exist', 'msisdn' => '218920920110', 'status' => 'active', 'expiration_date' => '2023-08-21 11:57:34', 'operator' => 'Libyana', 'ft' => 999]]]]);

    $challenge = app(ConnexClient::class)->login('0920920110', 'c136d6c0', DeviceType::Android);

    expect($challenge->subscriber)->toBe(SubscriberState::Existing)
        ->and($challenge->status)->toBe(SubscriptionStatus::Active)
        ->and($challenge->expiresAt?->toDateTimeString())->toBe('2023-08-21 09:57:34')
        ->and($challenge->requiresPlanSelection())->toBeFalse();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/login')
        && $request['msisdn'] === '218920920110'
        && $request['transaction_identify'] === 'c136d6c0'
        && $request['device_type'] === 'android'
        && $request['dcb_script_owner'] === 'my-evina-account'
        && ! isset($request['otp_signature']));
});

it('returns the plan list when Connex asks the subscriber to choose', function (): void {
    ConnexHttp::fake(['login' => [['messageCode' => '02', 'success' => ['message' => 'get subscription list', 'msisdn' => '218920920100', 'list' => [
        ['uuid' => 'weekly-uuid', 'name' => 'weekly', 'period' => 168, 'free_period' => 72, 'product_id' => 8, 'product' => ['cost' => 1.7, 'id' => 8]],
        ['uuid' => 'monthly-uuid', 'name' => 'monthly', 'period' => 720, 'free_period' => 168, 'product_id' => 9, 'product' => ['cost' => 6.5, 'id' => 9]],
    ]]], 300]]);

    $challenge = app(ConnexClient::class)->login('0920920100', 'tx');

    expect($challenge->requiresPlanSelection())->toBeTrue()
        ->and($challenge->plans[1]->uuid)->toBe('monthly-uuid')
        ->and($challenge->plans[1]->cost)->toBe(6.5)
        ->and($challenge->plans[0]->periodHours)->toBe(168);
});

it('raises the right exception for each login rejection', function (array $body, int $status, string $exception): void {
    ConnexHttp::fake(['login' => [$body, $status]]);

    expect(fn () => app(ConnexClient::class)->login('0920920110', 'tx'))->toThrow($exception);
})->with([
    'fraud' => [['messageCode' => '300', 'failed' => ['message' => 'This operation is not allowed']], 200, FraudDetected::class],
    'rate limited' => [['messageCode' => '05', 'failed' => ['message' => 'you have one OTP every 10 seconds']], 429, TooManyRequests::class],
]);

it('confirms an existing subscriber', function (): void {
    ConnexHttp::fake(['login-confirm' => [['messageCode' => '00', 'success' => ['message' => 'OTP confirmed', 'subscriber' => 'exist', 'msisdn' => '218920920110', 'status' => 'active', 'operator' => 'Libyana', 'expiration_date' => '2032-08-11 15:56:54']]]]);

    $confirmation = app(ConnexClient::class)->confirmLogin('0920920110', '4567', DeviceType::Ios, '1254', '99');

    expect($confirmation->isNewSubscriber())->toBeFalse()
        ->and($confirmation->status)->toBe(SubscriptionStatus::Active)
        ->and($confirmation->charge)->toBeNull();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/login-confirm')
        && $request['otp'] === '4567'
        && $request['device_type'] === 'ios'
        && $request['campaign_id'] === '1254'
        && $request['tracking_id'] === '99');
});

it('confirms a subscriber Connex just created (code 02, HTTP 201)', function (): void {
    ConnexHttp::fake(['login-confirm' => [['messageCode' => '02', 'success' => ['message' => 'OTP confirmed', 'subscriber' => 'created', 'msisdn' => '218920920110', 'operator' => 'Libyana']], 201]]);

    $confirmation = app(ConnexClient::class)->confirmLogin('0920920110', '4567');

    expect($confirmation->isNewSubscriber())->toBeTrue()
        ->and($confirmation->subscriber)->toBe(SubscriberState::Created);
});

it('reads the initial bill charged on confirmation', function (): void {
    ConnexHttp::fake(['login-confirm' => [['messageCode' => '00', 'success' => ['message' => 'OTP confirmed & purchase completed successfully', 'amount' => 0.5, 'connex_transaction_id' => 189756324, 'transactions_date' => '2032-08-11 15:56:54', 'msisdn' => '218920920110', 'operator' => 'Libyana']], 201]]);

    $confirmation = app(ConnexClient::class)->confirmLogin('0920920110', '4567');

    expect($confirmation->charge?->transactionId)->toBe('189756324')
        ->and($confirmation->charge?->amount)->toBe(0.5)
        ->and($confirmation->status)->toBe(SubscriptionStatus::Active);
});

it('rejects a wrong OTP and an empty balance', function (array $body, int $status, string $exception): void {
    ConnexHttp::fake(['login-confirm' => [$body, $status]]);

    expect(fn () => app(ConnexClient::class)->confirmLogin('0920920110', '0000'))->toThrow($exception);
})->with([
    'wrong otp' => [['messageCode' => '01', 'failed' => ['message' => 'OTP incorrect']], 422, InvalidOtp::class],
    'no balance' => [['messageCode' => '21', 'failed' => ['message' => 'not enough balance']], 406, InsufficientBalance::class],
]);
