<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Enums;

/**
 * Whether Connex already knew the subscriber (`subscriber` field).
 */
enum SubscriberState: string
{
    case New = 'new';
    case Existing = 'exist';
    case Created = 'created';

    public function isNew(): bool
    {
        return $this !== self::Existing;
    }
}
