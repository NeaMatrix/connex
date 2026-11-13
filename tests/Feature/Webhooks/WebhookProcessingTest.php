<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use NeaMatrix\Connex\Enums\ChargeType;
use NeaMatrix\Connex\Enums\SubscriptionStatus;
use NeaMatrix\Connex\Models\ConnexSubscription;
use NeaMatrix\Connex\Webhooks\Events\SubscriberReactivated;
use NeaMatrix\Connex\Webhooks\Events\SubscriberSubscribed;
use NeaMatrix\Connex\Webhooks\Events\SubscriberUnsubscribed;
use NeaMatrix\Connex\Webhooks\Events\SubscriptionRenewed;

function webhook(string $type, array $payload): TestResponse
{
    return test()->post("/connex/webhooks/{$type}?token=webhook-secret", $payload, ['Accept' => 'application/json']);
}

it('records an SMS subscription and dispatches SubscriberSubscribed', function (): void {
    Event::fake();

    webhook('subscribe', [
        'msisdn' => '218920920110', 'created_at' => '2023-09-18 00:05:25', 'device_type' => 'sms',
        'service_name' => 'Tawajah', 'subscription' => 'Daily', 'expiration_date' => '2023-09-19 00:05:25',
    ])->assertOk()->assertExactJson(['messageCode' => '00', 'message' => 'successfull subscribe']);

    $subscription = ConnexSubscription::query()->sole();
    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->plan_name)->toBe('Daily')
        ->and($subscription->expires_at?->toDateTimeString())->toBe('2023-09-18 22:05:25');

    Event::assertDispatched(SubscriberSubscribed::class, fn (SubscriberSubscribed $event): bool => $event->notification->channel === 'sms'
        && $event->subscription?->is($subscription) === true);
});

it('normalises the msisdn Connex sends', function (): void {
    webhook('subscribe', ['msisdn' => '0920920110'])->assertOk();

    expect(ConnexSubscription::query()->sole()->msisdn)->toBe('218920920110');
});

it('records cancellation and reactivation', function (): void {
    Event::fake();
    ConnexSubscription::factory()->create(['msisdn' => '218920920110']);

    webhook('unsubscribe', ['msisdn' => '218920920110', 'cancel_date' => '2023-09-18 00:05:25', 'cancel_by' => 'sms', 'status' => 'cancel'])
        ->assertJson(['message' => 'successfull unsubscribe']);
    expect(ConnexSubscription::query()->sole())
        ->status->toBe(SubscriptionStatus::Cancelled)
        ->cancelled_by->toBe('sms');

    webhook('reactivate', ['msisdn' => '218920920110', 'reactivate_date' => '2023-09-19 00:05:25', 'reactivate_by' => 'sms'])
        ->assertJson(['message' => 'successfull reactivate']);
    expect(ConnexSubscription::query()->sole()->status)->toBe(SubscriptionStatus::Active);

    Event::assertDispatched(SubscriberUnsubscribed::class);
    Event::assertDispatched(SubscriberReactivated::class);
});

it('records each renewal charge once, even when Connex retries', function (): void {
    Event::fake();
    $renewal = [
        'msisdn' => '218920920110', 'debit_date' => '2023-09-18 00:05:25', 'expiration_date' => '2023-09-19 00:05:25',
        'transaction_id' => '56795', 'conversation_id' => '36987', 'amount' => '0.25', 'service_name' => 'Tawajah',
    ];

    webhook('renewal', $renewal)->assertOk();
    webhook('renewal', $renewal)->assertOk()->assertJson(['message' => 'successfull renewal']);

    $subscription = ConnexSubscription::query()->sole();
    expect($subscription->charges)->toHaveCount(1)
        ->and($subscription->charges->first()?->type)->toBe(ChargeType::Renewal)
        ->and($subscription->charges->first()?->amount)->toBe('0.250')
        ->and($subscription->last_charged_at)->not->toBeNull();

    Event::assertDispatchedTimes(SubscriptionRenewed::class, 1);
});

it('only dispatches events when the local mirror is disabled', function (): void {
    config(['connex.subscriptions.store' => false]);
    Event::fake();

    webhook('renewal', ['msisdn' => '218920920110', 'transaction_id' => 't1', 'amount' => 0.25])->assertOk();

    expect(ConnexSubscription::query()->count())->toBe(0);
    Event::assertDispatched(SubscriptionRenewed::class, fn (SubscriptionRenewed $event): bool => $event->subscription === null);
});
