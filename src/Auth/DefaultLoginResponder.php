<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use LogicException;
use NeaMatrix\Connex\Contracts\LoginResponder;
use NeaMatrix\Connex\Login\LoginSession;

/**
 * Web: logs the user into the session guard and returns the intended URL.
 * App webview (device=android|ios): returns the app callback URL with a
 * single-use handoff code, which the app exchanges for an API token.
 */
class DefaultLoginResponder implements LoginResponder
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly HandoffCodes $handoffCodes,
        private readonly ?string $guard,
        private readonly string $redirectTo,
        private readonly ?string $mobileCallbackUrl,
    ) {}

    public function respond(Request $request, Authenticatable $user, LoginSession $session): string
    {
        $device = $session->device();

        if ($device->isMobile()) {
            if ($this->mobileCallbackUrl === null) {
                throw new LogicException('Set CONNEX_MOBILE_CALLBACK_URL to log in from the mobile app.');
            }

            $code = $this->handoffCodes->issue($user->getAuthIdentifier(), $device);
            $separator = str_contains($this->mobileCallbackUrl, '?') ? '&' : '?';

            return $this->mobileCallbackUrl.$separator.http_build_query(['code' => $code]);
        }

        $this->auth->guard($this->guard)->login($user, remember: true);
        $request->session()->regenerate();

        return (string) $request->session()->pull('url.intended', $this->redirectTo);
    }
}
