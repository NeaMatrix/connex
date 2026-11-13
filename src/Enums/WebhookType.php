<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Enums;

/**
 * The notifications Connex pushes to the service (Connex-notifications collection).
 */
enum WebhookType: string
{
    case Subscribe = 'subscribe';
    case Unsubscribe = 'unsubscribe';
    case Reactivate = 'reactivate';
    case Renewal = 'renewal';

    /** The acknowledgement body Connex expects back. */
    public function acknowledgement(): string
    {
        return 'successfull '.$this->value;
    }
}
