<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use NeaMatrix\Connex\Enums\SubscriptionAccess;
use NeaMatrix\Connex\Enums\SubscriptionStatus;

it('applies the Connex content rules', function (?SubscriptionStatus $status, ?string $expiry, SubscriptionAccess $expected, bool $allowed, ?string $action): void {
    $now = CarbonImmutable::parse('2026-01-10 12:00');
    $expiresAt = $expiry ? CarbonImmutable::parse($expiry) : null;

    $access = SubscriptionAccess::resolve($status, $expiresAt, $now);

    expect($access)->toBe($expected)
        ->and($access->allowsAccess())->toBe($allowed)
        ->and($access->action())->toBe($action);
})->with([
    'active, paid up' => [SubscriptionStatus::Active, '2026-01-11', SubscriptionAccess::Active, true, null],
    'active, renewal failed' => [SubscriptionStatus::Active, '2026-01-09', SubscriptionAccess::Expired, false, 'top_up'],
    'cancelled, still paid' => [SubscriptionStatus::Cancelled, '2026-01-11', SubscriptionAccess::Inactive, true, 'reactivate'],
    'cancelled and past expiry' => [SubscriptionStatus::Cancelled, '2026-01-09', SubscriptionAccess::Canceled, false, 'reactivate'],
    'active without expiry' => [SubscriptionStatus::Active, null, SubscriptionAccess::Expired, false, 'top_up'],
    'awaiting sms confirmation' => [SubscriptionStatus::Pending, '2026-01-11', SubscriptionAccess::None, false, 'subscribe'],
    'never subscribed' => [null, null, SubscriptionAccess::None, false, 'subscribe'],
]);

it('only gives a notice when the subscription is not active', function (): void {
    expect(SubscriptionAccess::Active->message())->toBeNull()
        ->and(SubscriptionAccess::Inactive->message())->toBe(__('connex::access.inactive'))
        ->and(SubscriptionAccess::Expired->message())->toContain('top up');
});
