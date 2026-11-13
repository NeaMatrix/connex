<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Subscriptions;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use NeaMatrix\Connex\Data\Charge;
use NeaMatrix\Connex\Data\LoginConfirmation;
use NeaMatrix\Connex\Data\SubscriberDetails;
use NeaMatrix\Connex\Data\SubscriptionChange;
use NeaMatrix\Connex\Enums\ChargeType;
use NeaMatrix\Connex\Enums\SubscriptionStatus;
use NeaMatrix\Connex\Models\ConnexSubscription;
use NeaMatrix\Connex\Support\ConnexModels;
use NeaMatrix\Connex\Support\Msisdn;

/**
 * Keeps the local connex_subscriptions mirror in step with Connex.
 *
 * Every write goes through {@see record()}, which upserts by msisdn and ignores
 * null values so a partial update never erases known data.
 */
class SubscriptionStore
{
    public function find(Msisdn|string $msisdn): ?ConnexSubscription
    {
        return ConnexModels::subscription()::query()->where('msisdn', (string) $msisdn)->first();
    }

    public function forOwner(Model $owner): ?ConnexSubscription
    {
        return ConnexModels::subscription()::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(Msisdn|string $msisdn, array $attributes): ConnexSubscription
    {
        $subscription = $this->find($msisdn) ?? new (ConnexModels::subscription())(['msisdn' => (string) $msisdn]);

        $subscription->fill(array_filter($attributes, static fn (mixed $value): bool => $value !== null));
        $subscription->synced_at = CarbonImmutable::now();
        $subscription->save();

        return $subscription;
    }

    public function recordLogin(LoginConfirmation $confirmation): ConnexSubscription
    {
        $subscription = $this->record($confirmation->msisdn, [
            'status' => $confirmation->status,
            'operator' => $confirmation->operator,
            'expires_at' => $confirmation->expiresAt,
        ]);

        if ($confirmation->charge !== null) {
            $this->recordCharge($subscription, $confirmation->charge, ChargeType::InitialBill);
        }

        return $subscription;
    }

    public function recordDetails(SubscriberDetails $details): ConnexSubscription
    {
        return $this->record($details->msisdn, [
            'status' => $details->status,
            'expires_at' => $details->expiresAt,
            'plan_name' => $details->planName,
            'cancelled_at' => $details->lastCancelledAt,
            'cancelled_by' => $details->cancelledBy,
            'last_charged_at' => $details->lastChargedAt,
        ]);
    }

    public function recordChange(SubscriptionChange $change): ConnexSubscription
    {
        return $this->record($change->msisdn, [
            'status' => $change->status,
            'expires_at' => $change->expiresAt,
            'cancelled_at' => $change->status === SubscriptionStatus::Cancelled ? CarbonImmutable::now() : null,
            'reactivated_at' => $change->status === SubscriptionStatus::Active ? CarbonImmutable::now() : null,
        ]);
    }

    /**
     * Store a charge once. Returns false when the transaction was already recorded,
     * which happens when Connex re-sends a renewal notification.
     */
    public function recordCharge(ConnexSubscription $subscription, Charge $charge, ChargeType $type): bool
    {
        if ($subscription->charges()->where('transaction_id', $charge->transactionId)->exists()) {
            return false;
        }

        try {
            $subscription->charges()->create([
                'transaction_id' => $charge->transactionId,
                'type' => $type,
                'amount' => $charge->amount,
                'invoice_no' => $charge->invoiceNo,
                'product_id' => $charge->productId,
                'charged_at' => $charge->chargedAt,
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        if ($charge->chargedAt !== null) {
            $subscription->forceFill(['last_charged_at' => $charge->chargedAt])->save();
        }

        return true;
    }

    public function attachOwner(ConnexSubscription $subscription, Model $owner): ConnexSubscription
    {
        $subscription->owner()->associate($owner)->save();

        return $subscription;
    }
}
