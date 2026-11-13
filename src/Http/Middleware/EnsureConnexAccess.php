<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use NeaMatrix\Connex\Enums\SubscriptionAccess;
use NeaMatrix\Connex\Subscriptions\SubscriptionStore;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks paid routes for users whose Connex subscription does not grant access.
 *
 * Route usage: ->middleware('connex.access')
 * JSON requests get HTTP 402 with an "access" reason; others are redirected to
 * the configured account page.
 */
class EnsureConnexAccess
{
    public function __construct(
        private readonly SubscriptionStore $subscriptions,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $subscription = $user instanceof Model ? $this->subscriptions->forOwner($user) : null;
        $access = $subscription?->access() ?? SubscriptionAccess::None;

        if ($access->allowsAccess()) {
            return $next($request);
        }

        $message = __("connex::access.{$access->value}");

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'access' => $access->value], 402);
        }

        $redirect = config('connex.account.enabled') ? route('connex.account') : '/';

        return redirect()->to($redirect)->with('connex.access', $access->value);
    }
}
