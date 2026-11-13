<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Login\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NeaMatrix\Connex\Auth\HandoffCodes;
use NeaMatrix\Connex\Contracts\IssuesTokens;

/**
 * POST /connex/api/handoff {"code": "..."} → {"token": "...", "token_type": "Bearer"}
 *
 * Called by the mobile app after the webview redirected to its callback URL.
 */
class HandoffController
{
    public function __invoke(Request $request, HandoffCodes $codes, IssuesTokens $tokens): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'size:64']]);

        $entry = $codes->redeem($request->string('code')->toString());

        /** @var class-string<Model> $model */
        $model = config('connex.user.model');
        $user = $entry === null ? null : $model::query()->find($entry['user']);

        if ($entry === null || ! $user instanceof Authenticatable) {
            return new JsonResponse(['error' => 'invalid_code', 'message' => __('connex::errors.invalid_code')], 422);
        }

        return new JsonResponse($tokens->issue($user, $entry['device'])->toArray());
    }
}
