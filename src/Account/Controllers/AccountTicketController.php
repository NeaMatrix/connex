<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Account\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use NeaMatrix\Connex\Auth\OneTimeCodes;
use NeaMatrix\Connex\Enums\DeviceType;
use NeaMatrix\Connex\Login\LoginSession;

/**
 * Lets a token-based app open the account page in a webview:
 *
 * 1. POST /connex/api/account-link (Bearer token) → {"url": ".../connex/account/enter?ticket=..."}
 * 2. The app opens that URL; the ticket signs the webview in and shows the account page.
 */
class AccountTicketController
{
    private const string PURPOSE = 'account-ticket';

    public function __construct(
        private readonly OneTimeCodes $codes,
    ) {}

    public function issue(Request $request): JsonResponse
    {
        /** @var Model&Authenticatable $user */
        $user = $request->user();
        $device = DeviceType::tryFrom($request->string('device')->toString()) ?? DeviceType::Web;
        $locale = $request->string('lang')->toString();

        $ticket = $this->codes->issue(self::PURPOSE, [
            'user' => $user->getKey(),
            'device' => $device->value,
        ], (int) config('connex.account.ticket_ttl', 120));

        $url = route('connex.account.enter', array_filter(['ticket' => $ticket, 'lang' => $locale]));

        return new JsonResponse(['url' => $url, 'expires_in' => (int) config('connex.account.ticket_ttl', 120)], 200, ['Cache-Control' => 'no-store']);
    }

    public function enter(Request $request, AuthFactory $auth, LoginSession $session): RedirectResponse
    {
        $payload = $this->codes->redeem(self::PURPOSE, $request->string('ticket')->toString());

        /** @var class-string<Model> $model */
        $model = config('connex.user.model');
        $user = isset($payload['user']) ? $model::query()->find($payload['user']) : null;

        abort_unless($user instanceof Authenticatable, 403, __('connex::errors.invalid_code'));

        $auth->guard(config('connex.account.guard'))->login($user);
        $request->session()->regenerate();
        $session->begin(DeviceType::tryFrom((string) ($payload['device'] ?? '')) ?? DeviceType::Web);

        return new RedirectResponse(route('connex.account', array_filter(['lang' => $request->query('lang')])));
    }
}
