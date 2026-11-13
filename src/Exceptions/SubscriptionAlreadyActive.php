<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Exceptions;

/**
 * Activation was requested for a subscription that is already active.
 */
class SubscriptionAlreadyActive extends RequestRejected {}
