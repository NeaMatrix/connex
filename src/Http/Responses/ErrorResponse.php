<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Http\Responses;

use Illuminate\Http\JsonResponse;
use NeaMatrix\Connex\Exceptions\ConnexException;
use NeaMatrix\Connex\Exceptions\FraudDetected;
use NeaMatrix\Connex\Exceptions\InsufficientBalance;
use NeaMatrix\Connex\Exceptions\InvalidMsisdn;
use NeaMatrix\Connex\Exceptions\InvalidOtp;
use NeaMatrix\Connex\Exceptions\RequestRejected;
use NeaMatrix\Connex\Exceptions\SubscriptionAlreadyActive;
use NeaMatrix\Connex\Exceptions\SubscriptionAlreadyCancelled;
use NeaMatrix\Connex\Exceptions\TooManyRequests;
use NeaMatrix\Connex\Login\LoginExpired;
use NeaMatrix\Connex\Login\PlanSelectionRequired;
use Throwable;

/**
 * Turns a failure from any step into a JSON body the bundled pages can show:
 * {"error": "invalid_otp", "message": "<translated text>"}.
 */
final class ErrorResponse
{
    /**
     * @return JsonResponse|null null when the throwable is not one this package handles
     */
    public static function from(Throwable $error): ?JsonResponse
    {
        [$key, $status] = match (true) {
            $error instanceof InvalidMsisdn => ['invalid_msisdn', 422],
            $error instanceof LoginExpired => ['session_expired', 419],
            $error instanceof PlanSelectionRequired => ['plan_selection_required', 409],
            $error instanceof InvalidOtp => ['invalid_otp', 422],
            $error instanceof TooManyRequests => ['too_many_requests', 429],
            $error instanceof FraudDetected => ['fraud', 403],
            $error instanceof InsufficientBalance => ['insufficient_balance', 402],
            $error instanceof SubscriptionAlreadyActive => ['already_active', 409],
            $error instanceof SubscriptionAlreadyCancelled => ['already_cancelled', 409],
            $error instanceof RequestRejected => ['generic', 422],
            $error instanceof ConnexException => ['unavailable', 503],
            default => [null, 0],
        };

        if ($key === null) {
            return null;
        }

        if ($status === 503) {
            report($error);
        }

        return new JsonResponse(['error' => $key, 'message' => __("connex::errors.{$key}")], $status);
    }
}
