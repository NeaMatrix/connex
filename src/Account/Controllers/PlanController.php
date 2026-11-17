<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Account\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NeaMatrix\Connex\Account\Requests\PlanChangeRequest;
use NeaMatrix\Connex\Data\Charge;
use NeaMatrix\Connex\Data\SubscriptionPlan;
use NeaMatrix\Connex\Enums\DeviceType;
use NeaMatrix\Connex\Http\Responses\ErrorResponse;
use NeaMatrix\Connex\Login\LoginSession;
use NeaMatrix\Connex\Subscriptions\NotSubscribed;
use NeaMatrix\Connex\Subscriptions\PlanSummary;
use NeaMatrix\Connex\Subscriptions\SubscriptionManager;
use NeaMatrix\Connex\Subscriptions\SubscriptionSummary;
use Throwable;

/**
 * Packages, plan changes (OTP-confirmed) and payment history for the signed-in
 * user, for the account page (session) and apps (Sanctum).
 */
class PlanController
{
    public function __construct(
        private readonly SubscriptionManager $manager,
        private readonly LoginSession $session,
    ) {}

    /** GET …/subscription/plans */
    public function index(Request $request): JsonResponse
    {
        return $this->attempt(fn (): JsonResponse => new JsonResponse([
            'plans' => array_map(fn (SubscriptionPlan $plan): array => PlanSummary::plan($plan), $this->manager->plans($this->owner($request))),
        ]));
    }

    /** POST …/subscription/plan-change {uuid}: Connex texts an OTP. */
    public function request(PlanChangeRequest $request): JsonResponse
    {
        return $this->attempt(function () use ($request): JsonResponse {
            $challenge = $this->manager->requestPlanChange($this->owner($request), $request->planUuid());

            return new JsonResponse(['otp_sent' => true, 'message' => $challenge->message]);
        });
    }

    /** POST …/subscription/plan-change/confirm {uuid, otp} */
    public function confirm(PlanChangeRequest $request): JsonResponse
    {
        return $this->attempt(function () use ($request): JsonResponse {
            $device = DeviceType::tryFrom($request->string('device')->toString()) ?? $this->session->device();
            $subscription = $this->manager->confirmPlanChange($this->owner($request), $request->otp(), $request->planUuid(), $device);

            return new JsonResponse([
                'message' => __('connex::account.plan_changed'),
                'subscription' => SubscriptionSummary::of($subscription),
            ]);
        });
    }

    /** GET …/subscription/transactions */
    public function transactions(Request $request): JsonResponse
    {
        return $this->attempt(fn (): JsonResponse => new JsonResponse([
            'transactions' => array_map(fn (Charge $charge): array => PlanSummary::charge($charge), $this->manager->transactions($this->owner($request))),
        ]));
    }

    private function owner(Request $request): Model
    {
        /** @var Model */
        return $request->user();
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
