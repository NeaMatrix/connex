<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the subscriber switch the Connex pages between Arabic and English
 * with ?lang=ar|en; the choice is remembered in the session.
 */
class SetConnexLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $locales */
        $locales = config('connex.pages.locales', ['ar', 'en']);
        $requested = $request->query('lang');

        if (is_string($requested) && in_array($requested, $locales, true)) {
            $request->session()->put('connex.locale', $requested);
        }

        $locale = $request->session()->get('connex.locale', config('connex.pages.default_locale'));

        if (is_string($locale) && in_array($locale, $locales, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
