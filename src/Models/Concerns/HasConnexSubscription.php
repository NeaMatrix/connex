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
        return $this->connexSubscription?->access() ?? SubscriptionAccess::None;
    }

    public function hasConnexAccess(): bool
    {
        return $this->connexAccess()->allowsAccess();
    }
}
