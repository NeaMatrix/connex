<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Login\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use NeaMatrix\Connex\Enums\DeviceType;
use NeaMatrix\Connex\Login\LoginSession;

/**
 * GET /connex/login?device=android&campaign_id=...&tracking_id=...
 */
class LoginPageController
{
    public function __invoke(Request $request, LoginSession $session): View
    {
        $session->begin(
            DeviceType::tryFrom($request->string('device')->toString()) ?? DeviceType::Web,
            $request->string('campaign_id')->toString() ?: null,
            $request->string('tracking_id')->toString() ?: null,
        );

        /** @var view-string $view */
        $view = config('connex.login.view', 'connex::login');

        return view($view, [
            'targetElement' => (string) config('connex.login.target_element', 'connex-submit'),
            'otpLength' => (int) config('connex.otp_length', 4),
            'resendCooldown' => (int) config('connex.login.resend_cooldown', 60),
            'gatewayTimeout' => (int) config('connex.login.gateway_timeout_ms', 15000),
        ]);
    }
}
