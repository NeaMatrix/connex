<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Login\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use NeaMatrix\Connex\Contracts\LoginResponder;
use NeaMatrix\Connex\Http\Responses\ErrorResponse;
use NeaMatrix\Connex\Login\LoginFlow;
use NeaMatrix\Connex\Login\LoginSession;
use NeaMatrix\Connex\Login\Requests\ConfirmOtpRequest;
use NeaMatrix\Connex\Login\Requests\RequestOtpRequest;
use NeaMatrix\Connex\Support\MsisdnNormalizer;
use Throwable;

/**
 * JSON endpoints behind the bundled login page.
 */
class LoginController
{
    public function __construct(
        private readonly LoginFlow $flow,
        private readonly LoginSession $session,
    ) {}

    /** GET /connex/login/script */
    public function script(): JsonResponse
    {
        return $this->attempt(function (): JsonResponse {
            $target = (string) config('connex.login.target_element', 'connex-submit');
            $script = $this->flow->loadScript($this->session, '#'.$target);

            return new JsonResponse(['script' => $script->script]);
        });
    }

    /** POST /connex/login/otp */
    public function requestOtp(RequestOtpRequest $request, MsisdnNormalizer $msisdns): JsonResponse
    {
        $cooldown = (int) config('connex.login.resend_cooldown', 60);

        if (($wait = $this->session->resendAvailableIn($cooldown)) > 0) {
            return new JsonResponse([
                'error' => 'too_many_requests',
                'message' => __('connex::errors.too_many_requests'),
                'resend_in' => $wait,
            ], 429);
        }

        return $this->attempt(function () use ($request, $msisdns, $cooldown): JsonResponse {
            $challenge = $this->flow->requestOtp($this->session, $request->msisdn());

            return new JsonResponse([
                'msisdn' => $msisdns->normalize($challenge->msisdn)->national(),
                'awaits_sms_reply' => $challenge->awaitsSmsConfirmation(),
                'resend_in' => $cooldown,
            ]);
        });
    }

    /** POST /connex/login/otp/confirm */
    public function confirmOtp(ConfirmOtpRequest $request, LoginResponder $responder): JsonResponse
    {
        return $this->attempt(function () use ($request, $responder): JsonResponse {
            $result = $this->flow->confirmOtp($this->session, $request->otp());
            $redirect = $responder->respond($request, $result, $this->session);
            $this->session->finish();

            return new JsonResponse($redirect->toArray(), 200, ['Cache-Control' => 'no-store']);
        });
    }

    /** GET /connex/login/cancel: return to the client with error=access_denied. */
    public function cancel(): RedirectResponse
    {
        $authorization = $this->session->authorization();
        $this->session->finish();

        return $authorization === null
            ? new RedirectResponse((string) config('connex.pages.home_url', '/'))
            : new RedirectResponse($authorization->redirectWith(['error' => 'access_denied']));
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
