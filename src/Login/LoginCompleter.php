<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Login;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NeaMatrix\Connex\Contracts\LoginResponder;
use NeaMatrix\Connex\Enums\SubscriptionAccess;
use NeaMatrix\Connex\Exceptions\ConnexException;
use NeaMatrix\Connex\Subscriptions\SubscriptionManager;

/**
 * Finishes a login: hands it to the {@see LoginResponder}, or, when
 * login.require_access is on and the subscription grants no access, keeps the
 * subscriber on the page to activate first.
 */
class LoginCompleter
{
    public function __construct(
        private readonly LoginResponder $responder,
        private readonly SubscriptionManager $subscriptions,
        private readonly bool $requiresAccess = false,
    ) {}

    public function complete(Request $request, LoginResult $result, LoginSession $session): JsonResponse
    {
        if ($this->requiresAccess && ! ($access = $this->accessOf($result))->allowsAccess()) {
            $session->rememberPendingActivation($result->user->getKey(), $result->confirmation->isNewSubscriber());

            return self::activationStep($access);
        }

        $redirect = $this->responder->respond($request, $result, $session);
        $session->finish();

        return new JsonResponse($redirect->toArray(), 200, ['Cache-Control' => 'no-store']);
    }

    /**
     * {"step": "activation", "access": "expired", "message": "..."}: the page shows
     * the activate (or check balance) step.
     */
    public static function activationStep(SubscriptionAccess $access): JsonResponse
    {
        return new JsonResponse([
            'step' => 'activation',
            'access' => $access->value,
            'message' => __("connex::access.{$access->value}"),
        ], 200, ['Cache-Control' => 'no-store']);
    }

    /**
     * Connex's subscriber details are authoritative (a brand-new subscriber's
     * confirmation carries no expiry); the stored state is the fallback.
     */
    private function accessOf(LoginResult $result): SubscriptionAccess
    {
        try {
            $subscription = $this->subscriptions->refresh($result->user) ?? $result->subscription;
        } catch (ConnexException $exception) {
            report($exception);
            $subscription = $result->subscription;
        }

        return $subscription?->access()
            ?? SubscriptionAccess::resolve($result->confirmation->status, $result->confirmation->expiresAt);
    }
}
