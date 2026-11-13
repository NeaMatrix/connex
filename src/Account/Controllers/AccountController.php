<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Account\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use NeaMatrix\Connex\Exceptions\ConnexException;
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
    ): View {
        /** @var Model $user */
        $user = $request->user();

        try {
            $subscription = $manager->refresh($user);
        } catch (ConnexException $exception) {
            report($exception);
            $subscription = $store->forOwner($user);
        }

        $access = $request->session()->get('connex.access') ?? $subscription?->access()->value;
        $notice = $access !== null && $access !== 'granted' ? __("connex::access.{$access}") : null;

        /** @var view-string $view */
        $view = config('connex.account.view', 'connex::account');

        return view($view, [
            'subscription' => $subscription,
            'msisdn' => $subscription ? $msisdns->normalize($subscription->msisdn)->national() : null,
            'notice' => $notice,
            'otpLength' => (int) config('connex.otp_length', 4),
            'logoutUrl' => config('connex.account.logout_url'),
        ]);
    }
}
