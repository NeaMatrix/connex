<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use NeaMatrix\Connex\Enums\SubscriptionAccess;
use NeaMatrix\Connex\Enums\SubscriptionStatus;

it('applies the Connex content rules', function (?SubscriptionStatus $status, ?string $expiry, SubscriptionAccess $expected, bool $allowed): void {
    $now = CarbonImmutable::parse('2026-01-10 12:00');
    $expiresAt = $expiry ? CarbonImmutable::parse($expiry) : null;

    $access = SubscriptionAccess::resolve($status, $expiresAt, $now);

    expect($access)->toBe($expected)
        ->and($access->allowsAccess())->toBe($allowed);
})->with([
    'active, paid up' => [SubscriptionStatus::Active, '2026-01-11', SubscriptionAccess::Granted, true],
    'active, renewal failed' => [SubscriptionStatus::Active, '2026-01-09', SubscriptionAccess::InsufficientBalance, false],
    'cancelled, still paid' => [SubscriptionStatus::Cancelled, '2026-01-11', SubscriptionAccess::GracePeriod, true],
    'cancelled and expired' => [SubscriptionStatus::Cancelled, '2026-01-09', SubscriptionAccess::Expired, false],
    'active without expiry' => [SubscriptionStatus::Active, null, SubscriptionAccess::InsufficientBalance, false],
    'awaiting sms confirmation' => [SubscriptionStatus::Pending, '2026-01-11', SubscriptionAccess::None, false],
    'never subscribed' => [null, null, SubscriptionAccess::None, false],
]);
