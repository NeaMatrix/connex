<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Login\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use NeaMatrix\Connex\Enums\DeviceType;
use NeaMatrix\Connex\Login\Clients\AuthorizationRequest;
use NeaMatrix\Connex\Login\Clients\ClientRegistry;
use NeaMatrix\Connex\Login\Clients\InvalidAuthorizationRequest;
use NeaMatrix\Connex\Login\LoginSession;

/**
 * GET /connex/login?client=…&redirect_uri=…&state=…&code_challenge=…&device=android&lang=ar
 */
class LoginPageController
{
    public function __invoke(Request $request, LoginSession $session, ClientRegistry $clients): Response
    {
        try {
            $authorization = AuthorizationRequest::fromRequest($request, $clients);
        } catch (InvalidAuthorizationRequest $exception) {
            return $this->error($exception->getMessage());
        }

        if ($authorization === null && ! config('connex.login.allow_session_login', true)) {
            return $this->error('This login link is missing its client.');
        }

        $session->begin(
            DeviceType::tryFrom($request->string('device')->toString()) ?? DeviceType::Web,
            $authorization,
            $request->string('campaign_id')->toString() ?: null,
            $request->string('tracking_id')->toString() ?: null,
        );

        /** @var view-string $view */
        $view = config('connex.login.view', 'connex::login');

        return response()->view($view, [
            'targetElement' => (string) config('connex.login.target_element', 'connex-submit'),
            'otpLength' => (int) config('connex.otp_length', 4),
            'requireTerms' => (bool) config('connex.login.require_terms', true),
            'gatewayTimeout' => (int) config('connex.login.gateway_timeout_ms', 15000),
            'canCancel' => $authorization !== null,
        ])->header('Cache-Control', 'no-store');
    }

    private function error(string $reason): Response
    {
        return response()->view('connex::error', ['reason' => $reason], 400);
    }
}
