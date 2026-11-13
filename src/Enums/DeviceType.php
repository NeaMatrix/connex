<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Enums;

/**
 * The device the subscriber used, sent to Connex as `device_type`.
 */
enum DeviceType: string
{
    case Web = 'web';
    case Android = 'android';
    case Ios = 'ios';

    public function isMobile(): bool
    {
        return $this !== self::Web;
    }
}
