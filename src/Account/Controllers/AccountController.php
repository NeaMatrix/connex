<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Account\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use NeaMatrix\Connex\Data\SubscriptionPlan;
use NeaMatrix\Connex\Enums\SubscriptionStatus;
use NeaMatrix\Connex\Exceptions\ConnexException;
use NeaMatrix\Connex\Models\ConnexSubscription;
use NeaMatrix\Connex\Subscriptions\SubscriptionManager;
use NeaMatrix\Connex\Subscriptions\SubscriptionStore;
use NeaMatrix\Connex\Support\MsisdnNormalizer;

/**
 * GET /connex/account: the profile page Connex requires (number, status,
 * expiry, activate/cancel). Refreshes from Connex, falling back to the local mirror.
 */
class AccountController
{
    public function __invoke(
        Request $request,
        SubscriptionManager $manager,
        SubscriptionStore $store,
        MsisdnNormalizer $msisdns,
    ): Response {
        /** @var Model $user */
        $user = $request->user();

        try {
            $subscription = $manager->refresh($user);
        } catch (ConnexException $exception) {
            report($exception);
            $subscription = $store->forOwner($user);
        }

        $access = $request->session()->get('connex.access') ?? $subscription?->access()->value;
        $notice = $access !== null && $access !== 'active' ? __("connex::access.{$access}") : null;

        /** @var view-string $view */
        $view = config('connex.account.view', 'connex::account');

        return response()->view($view, [
            'subscription' => $subscription,
            'msisdn' => $subscription ? $msisdns->normalize($subscription->msisdn)->national() : null,
            'notice' => $notice,
            'otpLength' => (int) config('connex.otp_length', 4),
            'plans' => $this->plansFor($user, $subscription, $manager),
            'logoutUrl' => config('connex.account.logout_url'),
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Packages the subscriber can switch to; empty when Connex can't list them.
     *
     * @return list<SubscriptionPlan>
     */
    private function plansFor(Model $user, ?ConnexSubscription $subscription, SubscriptionManager $manager): array
    {
        if ($subscription?->status !== SubscriptionStatus::Active) {
            return [];
        }

        try {
            return $manager->plans($user);
        } catch (ConnexException $exception) {
            report($exception);

            return [];
        }
    }
}
