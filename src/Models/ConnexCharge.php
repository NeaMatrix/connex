<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NeaMatrix\Connex\Enums\ChargeType;
use NeaMatrix\Connex\Support\ConnexModels;

/**
 * One debit from a subscriber's balance. transaction_id is unique, which
 * makes renewal notifications idempotent.
 *
 * @property int $id
 * @property int $connex_subscription_id
 * @property string $transaction_id
 * @property ChargeType $type
 * @property string $amount
 * @property string|null $invoice_no
 * @property int|null $product_id
 * @property CarbonImmutable|null $charged_at
 */
class ConnexCharge extends Model
{
    protected $table = 'connex_charges';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ChargeType::class,
            'amount' => 'decimal:3',
            'charged_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<ConnexSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(ConnexModels::subscription(), 'connex_subscription_id');
    }
}
