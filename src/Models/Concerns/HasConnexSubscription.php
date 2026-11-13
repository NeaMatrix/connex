<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use NeaMatrix\Connex\Enums\SubscriptionAccess;
use NeaMatrix\Connex\Models\ConnexSubscription;
use NeaMatrix\Connex\Support\ConnexModels;

/**
 * Add to the user model to reach its Connex subscription.
 *
 * Safe with Model::preventLazyLoading(): the helpers use the loaded relation
 * when it is there and query otherwise.
 *
 * @mixin Model
 */
trait HasConnexSubscription
{
    /**
     * @return MorphOne<ConnexSubscription, $this>
     */
    public function connexSubscription(): MorphOne
    {
        return $this->morphOne(ConnexModels::subscription(), 'owner');
    }

    public function connexAccess(): SubscriptionAccess
    {
        $subscription = $this->relationLoaded('connexSubscription')
            ? $this->getRelation('connexSubscription')
            : $this->connexSubscription()->first();

        return $subscription instanceof ConnexSubscription ? $subscription->access() : SubscriptionAccess::None;
    }

    public function hasConnexAccess(): bool
    {
        return $this->connexAccess()->allowsAccess();
    }
}
