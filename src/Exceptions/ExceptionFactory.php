<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Exceptions;

use NeaMatrix\Connex\Client\ConnexResponse;
use NeaMatrix\Connex\Enums\Endpoint;
use NeaMatrix\Connex\Enums\MessageCode;

/**
 * Maps a rejected Connex response to the most specific exception.
 */
final class ExceptionFactory
{
    public static function fromResponse(ConnexResponse $response): ConnexException
    {
        if ($response->endpoint === Endpoint::AuthLogin || $response->httpStatus === 401) {
            return AuthenticationFailed::fromResponse($response, 'Connex rejected the service credentials.');
        }

        if ($response->httpStatus === 429) {
            return TooManyRequests::fromResponse($response);
        }

        return match ($response->code) {
            MessageCode::FraudDetected => FraudDetected::fromResponse($response),
            MessageCode::TooManyRequests => TooManyRequests::fromResponse($response),
            MessageCode::InsufficientBalance => InsufficientBalance::fromResponse($response),
            MessageCode::AlreadyCancelled => SubscriptionAlreadyCancelled::fromResponse($response),
            MessageCode::InvalidProduct => InvalidProduct::fromResponse($response),
            MessageCode::Failed => self::failed($response),
            default => RequestRejected::fromResponse($response),
        };
    }

    private static function failed(ConnexResponse $response): ConnexException
    {
        return match (true) {
            $response->endpoint->confirmsOtp() => InvalidOtp::fromResponse($response),
            $response->endpoint === Endpoint::SubscriptionActivation => SubscriptionAlreadyActive::fromResponse($response),
            default => RequestRejected::fromResponse($response),
        };
    }
}
