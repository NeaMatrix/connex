<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Exceptions;

/**
 * Cancellation was requested for a subscription that is already cancelled.
 */
class SubscriptionAlreadyCancelled extends RequestRejected {}
