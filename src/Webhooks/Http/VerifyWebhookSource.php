<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Webhooks\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Connex does not sign its notifications, so the webhook URL carries a shared
 * secret (?token=... or the X-Connex-Token header) and may be limited to
 * Connex's IP addresses.
 */
class VerifyWebhookSource
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('connex.webhooks.secret');

        if (! is_string($secret) || $secret === '') {
            return $this->reject('webhook secret is not configured', 503);
        }

        $token = (string) ($request->header('X-Connex-Token') ?? $request->query('token', ''));

        if (! hash_equals($secret, $token)) {
            return $this->reject('invalid token', 401);
        }

        /** @var list<string> $allowedIps */
        $allowedIps = config('connex.webhooks.allowed_ips', []);

        if ($allowedIps !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowedIps)) {
            return $this->reject('address not allowed', 403);
        }

        return $next($request);
    }

    private function reject(string $message, int $status): Response
    {
        return response()->json(['messageCode' => '01', 'message' => $message], $status);
    }
}
