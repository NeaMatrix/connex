<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Enums;

/**
 * The `messageCode` values Connex returns in every response body.
 *
 * The same code can mean different things on different endpoints (for example
 * "01" is a wrong OTP on confirm endpoints and wrong credentials on auth-login),
 * so callers interpret a code together with the endpoint that produced it.
 */
enum MessageCode: string
{
    case Success = '00';
    case Failed = '01';
    case ActionRequired = '02';
    case TooManyRequests = '05';
    case AlreadyCancelled = '12';
    case InvalidProduct = '18';
    case InsufficientBalance = '21';
    case FraudDetected = '300';

    public function isSuccessful(): bool
    {
        return $this === self::Success || $this === self::ActionRequired;
    }
}
