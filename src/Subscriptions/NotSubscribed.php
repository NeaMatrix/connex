<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Subscriptions;

use RuntimeException;

/**
 * The user has no Connex subscription linked to their account.
 */
class NotSubscribed extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No Connex subscription is linked to this user.');
    }
}
