<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Account\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NeaMatrix\Connex\Enums\DeviceType;
use NeaMatrix\Connex\Exceptions\ConnexException;
use NeaMatrix\Connex\Http\Responses\ErrorResponse;
use NeaMatrix\Connex\Login\LoginSession;
use NeaMatrix\Connex\Login\Requests\ConfirmOtpRequest;
use NeaMatrix\Connex\Models\ConnexSubscription;
use NeaMatrix\Connex\Subscriptions\NotSubscribed;
use NeaMatrix\Connex\Subscriptions\SubscriptionManager;
use NeaMatrix\Connex\Subscriptions\SubscriptionStore;
use NeaMatrix\Connex\Subscriptions\SubscriptionSummary;
use Throwable;

/**
 * JSON endpoints for the signed-in user's subscription, used by the account
 * page (session) and by apps (Sanctum) under /connex/api/subscription.
 */
class SubscriptionController
{
    public function __construct(
        private readonly SubscriptionManager $manager,
        private readonly LoginSession $session,
    ) {}

    /** The current subscription, refreshed from Connex when it answers. */
    public function show(Request $request, SubscriptionStore $store): JsonResponse
    {
        $owner = $this->owner($request);

        try {
            $subscription = $this->manager->refresh($owner);
        } catch (ConnexException $exception) {
            report($exception);
            $subscription = $store->forOwner($owner);
        }

        return new JsonResponse(['subscription' => SubscriptionSummary::of($subscription)]);
    }

    public function requestActivation(Request $request): JsonResponse
    {
        return $this->attempt(fn (): JsonResponse => $this->otpSent($this->manager->requestActivation($this->owner($request))->message));
    }

    public function confirmActivation(ConfirmOtpRequest $request): JsonResponse
    {
        return $this->attempt(fn (): JsonResponse => $this->changed(
            $this->manager->confirmActivation($this->owner($request), $request->otp(), $this->device($request)),
            __('connex::account.activated'),
        ));
    }

    public function requestCancellation(Request $request): JsonResponse
    {
        return $this->attempt(fn (): JsonResponse => $this->otpSent($this->manager->requestCancellation($this->owner($request))->message));
    }

    public function confirmCancellation(ConfirmOtpRequest $request): JsonResponse
    {
        return $this->attempt(fn (): JsonResponse => $this->changed(
            $this->manager->confirmCancellation($this->owner($request), $request->otp(), $this->device($request)),
            __('connex::account.cancelled'),
        ));
    }

    private function owner(Request $request): Model
    {
        /** @var Model */
        return $request->user();
    }

    private function device(Request $request): DeviceType
    {
        return DeviceType::tryFrom($request->string('device')->toString()) ?? $this->session->device();
    }

    private function otpSent(?string $message): JsonResponse
    {
        return new JsonResponse(['otp_sent' => true, 'message' => $message]);
    }

    private function changed(ConnexSubscription $subscription, string $message): JsonResponse
    {
        return new JsonResponse([
            'status' => $subscription->status?->value,
            'message' => $message,
            'subscription' => SubscriptionSummary::of($subscription),
        ]);
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
