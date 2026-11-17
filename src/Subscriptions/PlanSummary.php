<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Subscriptions;

use NeaMatrix\Connex\Data\Charge;
use NeaMatrix\Connex\Data\SubscriptionPlan;

/**
 * The JSON shapes of plans and charges in API responses.
 */
final class PlanSummary
{
    /**
     * @return array{uuid: string, name: string, period_hours: int, free_period_hours: int, product_id: int|null, cost: float|null}
     */
    public static function plan(SubscriptionPlan $plan): array
    {
        return [
            'uuid' => $plan->uuid,
            'name' => $plan->name,
            'period_hours' => $plan->periodHours,
            'free_period_hours' => $plan->freePeriodHours,
            'product_id' => $plan->productId,
            'cost' => $plan->cost,
        ];
    }

    /**
     * @return array{transaction_id: string, invoice_no: string|null, amount: float, status: string|null, charged_at: string|null}
     */
    public static function charge(Charge $charge): array
    {
        return [
            'transaction_id' => $charge->transactionId,
            'invoice_no' => $charge->invoiceNo,
            'amount' => $charge->amount,
            'status' => $charge->status,
            'charged_at' => $charge->chargedAt?->toIso8601String(),
        ];
    }
}
