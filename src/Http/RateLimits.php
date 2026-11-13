<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Http;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Named rate limiters for the Connex routes.
 *
 * Libyan carriers put many subscribers behind one public IP (carrier-grade
 * NAT), so the tight limits are per phone number or user, and the
 * per-IP limits only stop floods.
 */
final class RateLimits
{
    public static function register(): void
    {
        RateLimiter::for('connex-script', fn (Request $request): Limit => Limit::perMinute(self::limit('script_per_ip', 60))
            ->by('script|'.$request->ip()));

        RateLimiter::for('connex-otp', fn (Request $request): array => [
            Limit::perMinute(self::limit('otp_per_msisdn', 3))->by('otp|'.self::digits($request->input('msisdn'))),
            Limit::perMinute(self::limit('otp_per_ip', 60))->by('otp-ip|'.$request->ip()),
        ]);

        RateLimiter::for('connex-confirm', fn (Request $request): array => [
            Limit::perMinute(self::limit('confirm_per_msisdn', 5))->by('confirm|'.self::loginKey($request)),
            Limit::perMinute(self::limit('confirm_per_ip', 120))->by('confirm-ip|'.$request->ip()),
        ]);

        RateLimiter::for('connex-activation', fn (Request $request): Limit => Limit::perMinute(self::limit('otp_per_msisdn', 3))
            ->by('activation|'.self::loginKey($request)));

        RateLimiter::for('connex-token', fn (Request $request): Limit => Limit::perMinute(self::limit('token_per_ip', 60))
            ->by('token|'.$request->ip()));

        RateLimiter::for('connex-account-otp', fn (Request $request): Limit => Limit::perMinute(self::limit('otp_per_msisdn', 3))
            ->by('account-otp|'.self::userKey($request)));

        RateLimiter::for('connex-account-confirm', fn (Request $request): Limit => Limit::perMinute(self::limit('confirm_per_msisdn', 5))
            ->by('account-confirm|'.self::userKey($request)));
    }

    private static function limit(string $key, int $default): int
    {
        return (int) config("connex.login.throttle.{$key}", $default);
    }

    private static function digits(mixed $value): string
    {
        $digits = is_string($value) ? (preg_replace('/\D+/', '', $value) ?? '') : '';

        return substr($digits, -9);
    }

    /** The number being confirmed in this login, so new sessions don't reset the count. */
    private static function loginKey(Request $request): string
    {
        $msisdn = $request->session()->get('connex.login.msisdn');

        return is_string($msisdn) ? $msisdn : $request->session()->getId();
    }

    private static function userKey(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_scalar($id) ? (string) $id : (string) $request->ip();
    }
}
