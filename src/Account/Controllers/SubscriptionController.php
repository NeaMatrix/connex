<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Account\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NeaMatrix\Connex\Http\Responses\ErrorResponse;
use NeaMatrix\Connex\Login\LoginSession;
use NeaMatrix\Connex\Login\Requests\ConfirmOtpRequest;
use NeaMatrix\Connex\Subscriptions\NotSubscribed;
use NeaMatrix\Connex\Subscriptions\SubscriptionManager;
use Throwable;

/**
 * JSON endpoints for activating and cancelling the signed-in user's subscription.
 */
class SubscriptionController
{
    public function __construct(
        private readonly SubscriptionManager $manager,
        private readonly LoginSession $session,
    ) {}

    public function requestActivation(Request $request): JsonResponse
    {
        return $this->attempt(fn (): JsonResponse => $this->otpSent($this->manager->requestActivation($this->owner($request))->message));
    }

    public function confirmActivation(ConfirmOtpRequest $request): JsonResponse
    {
        return $this->attempt(function () use ($request): JsonResponse {
            $subscription = $this->manager->confirmActivation($this->owner($request), $request->otp(), $this->session->device());

            return new JsonResponse(['status' => $subscription->status?->value, 'message' => __('connex::account.activated')]);
        });
    }

    public function requestCancellation(Request $request): JsonResponse
    {
        return $this->attempt(fn (): JsonResponse => $this->otpSent($this->manager->requestCancellation($this->owner($request))->message));
    }

    public function confirmCancellation(ConfirmOtpRequest $request): JsonResponse
    {
        return $this->attempt(function () use ($request): JsonResponse {
            $subscription = $this->manager->confirmCancellation($this->owner($request), $request->otp(), $this->session->device());

            return new JsonResponse(['status' => $subscription->status?->value, 'message' => __('connex::account.cancelled')]);
        });
    }

    private function owner(Request $request): Model
    {
        /** @var Model */
        return $request->user();
    }

    private function otpSent(?string $message): JsonResponse
    {
        return new JsonResponse(['otp_sent' => true, 'message' => $message]);
    }

    /**
     * @param  callable(): JsonResponse  $step
     */
    private function attempt(callable $step): JsonResponse
    {
        try {
            return $step();
        } catch (NotSubscribed) {
            return new JsonResponse(['error' => 'not_subscribed', 'message' => __('connex::errors.not_subscribed')], 404);
        } catch (Throwable $error) {
            return ErrorResponse::from($error) ?? throw $error;
        }
    }
}
