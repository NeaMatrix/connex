<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Auth;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;
use NeaMatrix\Connex\Contracts\LoginResponder;
use NeaMatrix\Connex\Login\Clients\AuthorizationRequest;
use NeaMatrix\Connex\Login\LoginRedirect;
use NeaMatrix\Connex\Login\LoginResult;
use NeaMatrix\Connex\Login\LoginSession;

/**
 * With a client (mobile app, web frontend): returns to its redirect_uri with a
 * single-use ?code= that the client exchanges for a token at POST /connex/api/token.
 *
 * Without a client: signs the visitor into the session guard and returns the
 * intended URL, for frontends served from this same app.
 */
class DefaultLoginResponder implements LoginResponder
{
    public const string CODE_PURPOSE = 'login-code';

    public function __construct(
        private readonly AuthFactory $auth,
        private readonly OneTimeCodes $codes,
        private readonly int $codeTtl,
        private readonly ?string $guard,
        private readonly string $redirectTo,
    ) {}

    public function respond(Request $request, LoginResult $result, LoginSession $session): LoginRedirect
    {
        $authorization = $session->authorization();

        return $authorization === null
            ? $this->sessionLogin($request, $result)
            : $this->codeFor($authorization, $result, $session);
    }

    private function codeFor(AuthorizationRequest $authorization, LoginResult $result, LoginSession $session): LoginRedirect
    {
        $grant = new LoginGrant(
            userId: $result->user->getKey(),
            clientId: $authorization->clientId,
            redirectUri: $authorization->redirectUri,
            device: $session->device(),
            isNewSubscriber: $result->confirmation->isNewSubscriber(),
            codeChallenge: $authorization->codeChallenge,
        );

        $code = $this->codes->issue(self::CODE_PURPOSE, $grant->toArray(), $this->codeTtl);
        $url = $authorization->redirectWith(['code' => $code]);

        if ($authorization->responseMode !== AuthorizationRequest::RESPONSE_WEB_MESSAGE) {
            return new LoginRedirect($url);
        }

        return new LoginRedirect(
            $url,
            ['type' => 'connex:login', 'code' => $code, 'state' => $authorization->state],
            $authorization->targetOrigin(),
        );
    }

    private function sessionLogin(Request $request, LoginResult $result): LoginRedirect
    {
        $this->auth->guard($this->guard)->login($result->user, remember: true);
        $request->session()->regenerate();

        return new LoginRedirect((string) $request->session()->pull('url.intended', $this->redirectTo));
    }
}
