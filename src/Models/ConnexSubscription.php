<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use NeaMatrix\Connex\Database\Factories\ConnexSubscriptionFactory;
use NeaMatrix\Connex\Enums\SubscriptionAccess;
use NeaMatrix\Connex\Enums\SubscriptionStatus;
use NeaMatrix\Connex\Support\ConnexModels;

/**
 * The local mirror of a subscriber's Connex subscription.
 *
 * @property int $id
 * @property string $msisdn
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property SubscriptionStatus|null $status
 * @property string|null $operator
 * @property string|null $plan_name
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $subscribed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancelled_by
 * @property CarbonImmutable|null $reactivated_at
 * @property CarbonImmutable|null $last_charged_at
 * @property CarbonImmutable|null $synced_at
 */
class ConnexSubscription extends Model
{
    /** @use HasFactory<ConnexSubscriptionFactory> */
    use HasFactory;

    protected $table = 'connex_subscriptions';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'expires_at' => 'immutable_datetime',
            'subscribed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'reactivated_at' => 'immutable_datetime',
            'last_charged_at' => 'immutable_datetime',
            'synced_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<ConnexCharge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(ConnexModels::charge());
    }

    public function access(): SubscriptionAccess
    {
        return SubscriptionAccess::resolve($this->status, $this->expires_at);
    }

    public function allowsAccess(): bool
    {
        return $this->access()->allowsAccess();
    }

    /**
     * Subscriptions whose holders may use paid content right now.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithAccess(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::Cancelled])
            ->where('expires_at', '>', now());
    }

    protected static function newFactory(): ConnexSubscriptionFactory
    {
        return ConnexSubscriptionFactory::new();
    }
}
