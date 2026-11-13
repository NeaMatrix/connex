<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Login\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NeaMatrix\Connex\Auth\DefaultLoginResponder;
use NeaMatrix\Connex\Auth\LoginGrant;
use NeaMatrix\Connex\Auth\OneTimeCodes;
use NeaMatrix\Connex\Auth\Pkce;
use NeaMatrix\Connex\Contracts\BuildsTokenResponse;
use NeaMatrix\Connex\Contracts\IssuesTokens;

/**
 * POST /connex/api/token {client, code, redirect_uri, code_verifier}
 *
 * Exchanges the one-time login code for an API token. The code must be used
 * by the same client, with the same redirect_uri and the PKCE verifier.
 */
class TokenController
{
    public function __invoke(
        Request $request,
        OneTimeCodes $codes,
        IssuesTokens $tokens,
        BuildsTokenResponse $responses,
    ): JsonResponse {
        $input = $request->validate([
            'client' => ['required', 'string'],
            'code' => ['required', 'string', 'size:64'],
            'redirect_uri' => ['required', 'string'],
            'code_verifier' => ['nullable', 'string', 'min:43', 'max:128'],
        ]);

        $payload = $codes->redeem(DefaultLoginResponder::CODE_PURPOSE, $input['code']);
        $grant = $payload === null ? null : LoginGrant::fromArray($payload);
        $user = $grant === null ? null : $this->findUser($grant);

        if ($grant === null || $user === null || ! $this->matches($grant, $input)) {
            return new JsonResponse([
                'error' => 'invalid_grant',
                'message' => __('connex::errors.invalid_code'),
            ], 400, ['Cache-Control' => 'no-store']);
        }

        $token = $tokens->issue($user, $grant->device);

        return new JsonResponse($responses->build($user, $token, $grant), 200, ['Cache-Control' => 'no-store']);
    }

    /**
     * @param  array{client: string, redirect_uri: string, code_verifier?: string|null}  $input
     */
    private function matches(LoginGrant $grant, array $input): bool
    {
        if (! hash_equals($grant->clientId, $input['client']) || ! hash_equals($grant->redirectUri, $input['redirect_uri'])) {
            return false;
        }

        return $grant->codeChallenge === null
            || Pkce::verify((string) ($input['code_verifier'] ?? ''), $grant->codeChallenge);
    }

    private function findUser(LoginGrant $grant): (Model&Authenticatable)|null
    {
        /** @var class-string<Model> $model */
        $model = config('connex.user.model');
        $user = $model::query()->find($grant->userId);

        return $user instanceof Authenticatable ? $user : null;
    }
}
