<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use NeaMatrix\Connex\Enums\SubscriptionStatus;
use NeaMatrix\Connex\Models\ConnexSubscription;

/**
 * @extends Factory<ConnexSubscription>
 */
class ConnexSubscriptionFactory extends Factory
{
    protected $model = ConnexSubscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'msisdn' => '21892'.fake()->unique()->numerify('#######'),
            'status' => SubscriptionStatus::Active,
            'operator' => 'Libyana',
            'plan_name' => 'Daily',
            'expires_at' => now()->addDay(),
            'subscribed_at' => now()->subWeek(),
            'synced_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => SubscriptionStatus::Active, 'expires_at' => now()->addDay()]);
    }

    /** Active on Connex, but the last renewal charge failed. */
    public function unpaid(): static
    {
        return $this->state(['status' => SubscriptionStatus::Active, 'expires_at' => now()->subHour()]);
    }

    /** Cancelled, still inside the paid period. */
    public function cancelled(): static
    {
        return $this->state(['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now(), 'expires_at' => now()->addDay()]);
    }

    public function expired(): static
    {
        return $this->state(['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now()->subWeek(), 'expires_at' => now()->subDay()]);
    }

    public function ownedBy(Model $owner): static
    {
        return $this->state(['owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey()]);
    }
}
