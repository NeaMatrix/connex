<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Support;

use NeaMatrix\Connex\Models\ConnexCharge;
use NeaMatrix\Connex\Models\ConnexSubscription;

/**
 * Resolves the model classes, which apps may swap in config("connex.models").
 */
final class ConnexModels
{
    /**
     * @return class-string<ConnexSubscription>
     */
    public static function subscription(): string
    {
        /** @var class-string<ConnexSubscription> */
        return config('connex.models.subscription', ConnexSubscription::class);
    }

    /**
     * @return class-string<ConnexCharge>
     */
    public static function charge(): string
    {
        /** @var class-string<ConnexCharge> */
        return config('connex.models.charge', ConnexCharge::class);
    }
}
