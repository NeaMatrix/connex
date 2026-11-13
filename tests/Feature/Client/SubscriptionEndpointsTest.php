<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use NeaMatrix\Connex\Contracts\ConnexClient;
use NeaMatrix\Connex\Enums\DeviceType;
use NeaMatrix\Connex\Enums\SubscriptionStatus;
use NeaMatrix\Connex\Exceptions\InvalidOtp;
use NeaMatrix\Connex\Exceptions\InvalidProduct;
use NeaMatrix\Connex\Exceptions\SubscriptionAlreadyActive;
use NeaMatrix\Connex\Exceptions\SubscriptionAlreadyCancelled;
use NeaMatrix\Connex\Tests\Fixtures\ConnexHttp;

it('reads subscriber details', function (): void {
    ConnexHttp::fake(['subscriber-details' => [['messageCode' => '00', 'success' => ['message' => 'get subscriber details', 'subscriber' => 'exist', 'msisdn' => '218920920110', 'details' => [
        'status' => 'active', 'expiration_date' => '2023-02-24 12:15:32', 'hoursRemind' => 23, 'last_login' => '2023-02-23 12:15:44',
        'device_type' => 'web', 'last_cancel' => null, 'canceled_by' => null, 'last_renew' => '2023-02-23 12:15:32', 'renew_by' => null,
        'subscription_name' => 'باقة اليومية', 'last_debit' => null,
    ]]]]]);

    $details = app(ConnexClient::class)->subscriberDetails('0920920110');

    expect($details->exists)->toBeTrue()
        ->and($details->status)->toBe(SubscriptionStatus::Active)
        ->and($details->hoursRemaining)->toBe(23)
        ->and($details->planName)->toBe('باقة اليومية')
        ->and($details->deviceType)->toBe(DeviceType::Web)
        ->and($details->lastCancelledAt)->toBeNull()
        ->and($details->expiresAt?->toDateTimeString())->toBe('2023-02-24 10:15:32');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && str_contains($request->url(), 'msisdn=218920920110'));
});

it('lists transactions and plans', function (): void {
    ConnexHttp::fake([
        'subscriber-transactions' => [['messageCode' => '00', 'success' => ['msisdn' => '218920920110', 'list' => [
            ['invoice_no' => '234566667', 'connex_transaction_id' => '167714935937624740049', 'amount' => 0.25, 'status' => 'success', 'transactions_date' => '2023-02-23 12:49:19'],
        ]]]],
        'subscription-list' => [['messageCode' => '00', 'success' => ['msisdn' => '218920920110', 'list' => [
            ['uuid' => 'e96bece0', 'name' => 'Daily', 'period' => 24, 'free_period' => 24, 'product_id' => 1, 'product' => ['cost' => 0.25, 'id' => 1]],
        ]]]],
    ]);
    $connex = app(ConnexClient::class);

    expect($connex->transactions('0920920110')[0]->transactionId)->toBe('167714935937624740049')
        ->and($connex->transactions('0920920110')[0]->invoiceNo)->toBe('234566667')
        ->and($connex->plans('0920920110')[0]->name)->toBe('Daily');
});

it('activates and cancels with OTP confirmation', function (): void {
    ConnexHttp::fake([
        'subscription-activation-confirm' => [['messageCode' => '00', 'success' => ['message' => 'subscription activated', 'msisdn' => '218920920110', 'status' => 'active', 'expiration_date' => '2023-02-24 12:15:32']]],
        'subscription-activation' => [['messageCode' => '00', 'success' => ['message' => 'OTP send success', 'subscriber' => 'exist', 'msisdn' => '218920920110', 'status' => 'cancel']]],
        'unsubscribe-confirm' => [['messageCode' => '00', 'success' => ['message' => 'subscription unsubscribed', 'msisdn' => '218920920110', 'status' => 'cancel']]],
        'unsubscribe' => [['messageCode' => '00', 'success' => ['message' => 'OTP send success', 'msisdn' => '218920920110', 'status' => 'active']]],
    ]);
    $connex = app(ConnexClient::class);

    expect($connex->requestActivation('0920920110')->message)->toBe('OTP send success')
        ->and($connex->confirmActivation('0920920110', '4567')->status)->toBe(SubscriptionStatus::Active)
        ->and($connex->requestCancellation('0920920110')->message)->toBe('OTP send success')
        ->and($connex->confirmCancellation('0920920110', '4567', DeviceType::Android)->status)->toBe(SubscriptionStatus::Cancelled);
});

it('maps activation and cancellation rejections', function (string $endpoint, array $body, int $status, callable $call, string $exception): void {
    ConnexHttp::fake([$endpoint => [$body, $status]]);

    expect(fn () => $call(app(ConnexClient::class)))->toThrow($exception);
})->with([
    'already active' => ['subscription-activation', ['messageCode' => '01', 'success' => ['message' => 'subscription is already active']], 406, fn (ConnexClient $c) => $c->requestActivation('0920920110'), SubscriptionAlreadyActive::class],
    'already cancelled' => ['unsubscribe', ['messageCode' => '12', 'failed' => ['message' => 'subscription is already cancel']], 406, fn (ConnexClient $c) => $c->requestCancellation('0920920110'), SubscriptionAlreadyCancelled::class],
    'wrong activation otp' => ['subscription-activation-confirm', ['messageCode' => '01', 'success' => ['message' => 'OTP incorrect']], 422, fn (ConnexClient $c) => $c->confirmActivation('0920920110', '0000'), InvalidOtp::class],
    'unknown product' => ['buy-product', ['messageCode' => '18', 'failed' => ['message' => 'product invalid']], 404, fn (ConnexClient $c) => $c->requestPurchase('0920920110', 3, 'inv-1', 'tx'), InvalidProduct::class],
]);

it('buys a product', function (): void {
    ConnexHttp::fake([
        'buy-product-confirm' => [['messageCode' => '00', 'success' => ['message' => 'purchase completed successfully', 'msisdn' => '218920920110', 'product_id' => '2', 'invoice_no' => 'inv-1', 'amount' => 0.25, 'connex_transaction_id' => '1677149411', 'transactions_date' => '2023-02-23T10:50:12.014544Z']]],
        'buy-product' => [['messageCode' => '00', 'success' => ['message' => 'OTP send success', 'product' => 'Direct', 'msisdn' => '218920920110']]],
    ]);
    $connex = app(ConnexClient::class);

    $connex->requestPurchase('0920920110', 2, 'inv-1', 'tx');
    $charge = $connex->confirmPurchase('0920920110', '4567', 'inv-1');

    expect($charge->transactionId)->toBe('1677149411')->and($charge->productId)->toBe(2)->and($charge->invoiceNo)->toBe('inv-1');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/buy-product')
        && $request['product_id'] === 2 && $request['invoice_no'] === 'inv-1' && $request['transaction_identify'] === 'tx');
});

it('changes plan and sends SMS templates', function (): void {
    ConnexHttp::fake([
        'upgrade-confirm' => [['messageCode' => '00', 'success' => ['message' => 'subscription upgraded', 'msisdn' => '218920920110']]],
        'upgrade' => [['messageCode' => '00', 'success' => ['message' => 'OTP send success', 'msisdn' => '218920920110']]],
        'send-sms-template' => [['messageCode' => '00', 'success' => ['message' => 'message send success', 'msisdn' => '218920920110', 'type' => 'account']]],
        'direct-unsubscribe' => [['messageCode' => '00', 'success' => ['msisdn' => '218920920110', 'status' => 'cancel']]],
    ]);
    $connex = app(ConnexClient::class);

    $connex->requestPlanChange('0920920110', 'plan-uuid');
    $connex->confirmPlanChange('0920920110', '4567', 'plan-uuid');
    $connex->sendSmsTemplate('0920920110', 'account');

    expect($connex->cancelImmediately('0920920110')->status)->toBe(SubscriptionStatus::Cancelled);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/upgrade-confirm') && $request['uuid'] === 'plan-uuid');
});
