<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Login\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NeaMatrix\Connex\Data\LoginConfirmation;
use NeaMatrix\Connex\Enums\SubscriberState;
use NeaMatrix\Connex\Http\Responses\ErrorResponse;
use NeaMatrix\Connex\Login\LoginCompleter;
use NeaMatrix\Connex\Login\LoginExpired;
use NeaMatrix\Connex\Login\LoginResult;
use NeaMatrix\Connex\Login\LoginSession;
use NeaMatrix\Connex\Login\Requests\ConfirmOtpRequest;
use NeaMatrix\Connex\Models\ConnexSubscription;
use NeaMatrix\Connex\Subscriptions\SubscriptionManager;
use Throwable;

/**
 * The activation step of the login page (login.require_access): a subscriber
 * whose subscription lapsed re-activates it with an OTP, then the login finishes.
 */
class ActivationController
{
    public function __construct(
        private readonly SubscriptionManager $subscriptions,
        private readonly LoginSession $session,
        private readonly LoginCompleter $completer,
    ) {}

    /** POST /connex/login/activation: Connex texts an activation OTP. */
    public function request(): JsonResponse
    {
        return $this->attempt(function (): JsonResponse {
            $challenge = $this->subscriptions->requestActivation($this->pendingUser());

            return new JsonResponse(['otp_sent' => true, 'message' => $challenge->message]);
        });
    }

    /** POST /connex/login/activation/confirm {otp} */
    public function confirm(ConfirmOtpRequest $request): JsonResponse
    {
        return $this->attempt(function () use ($request): JsonResponse {
            $user = $this->pendingUser();
            $subscription = $this->subscriptions->confirmActivation($user, $request->otp(), $this->session->device());

            return $this->finish($request, $user, $subscription);
        });
    }

    /** POST /connex/login/access/check: re-read Connex, e.g. after the subscriber topped up. */
    public function check(Request $request): JsonResponse
    {
        return $this->attempt(function () use ($request): JsonResponse {
            $user = $this->pendingUser();

            return $this->finish($request, $user, $this->subscriptions->refresh($user));
        });
    }

    private function finish(Request $request, Model&Authenticatable $user, ?ConnexSubscription $subscription): JsonResponse
    {
        if ($subscription === null) {
            throw LoginExpired::missing('subscription');
        }

        $confirmation = new LoginConfirmation(
            msisdn: $subscription->msisdn,
            subscriber: ($this->session->pendingActivation()['new'] ?? false) ? SubscriberState::Created : SubscriberState::Existing,
            status: $subscription->status,
            expiresAt: $subscription->expires_at,
            operator: $subscription->operator,
        );

        return $this->completer->complete($request, new LoginResult($user, $confirmation, $subscription), $this->session);
    }

    private function pendingUser(): Model&Authenticatable
    {
        $pending = $this->session->pendingActivation() ?? throw LoginExpired::missing('pending activation');

        /** @var class-string<Model> $model */
        $model = config('connex.user.model');
        $user = $model::query()->find($pending['user']);

        return $user instanceof Authenticatable ? $user : throw LoginExpired::missing('user');
    }

    /**
     * @param  callable(): JsonResponse  $step
     */
    private function attempt(callable $step): JsonResponse
    {
        try {
            return $step();
        } catch (Throwable $error) {
            return ErrorResponse::from($error) ?? throw $error;
        }
    }
}
