# The login page

Every project uses the same flow and logic. **What the page looks like is up to each project.**

1. A loader runs the Connex anti-fraud script and waits for `gateway-load`.
2. The subscriber enters their phone number (and accepts the terms, if `login.require_terms` is on).
3. They enter the OTP. There's a resend countdown, "Change number" and "Cancel".
4. They are sent back to the client (see [Apps and frontends](clients.md)), or signed into the session for same-domain apps.

Every error (wrong code, fraud, rate limit, insufficient balance, Connex down) is shown in the subscriber's language, `?lang=ar|en`.

## Branding without touching views

```php
'pages' => [
    'brand_name' => 'Tawajah',
    'brand_color' => '#0e7c66',
    'logo_url' => 'https://cdn.tawajah.ly/logo.svg',
    'stylesheets' => ['https://cdn.tawajah.ly/connex-theme.css'],
    'default_locale' => 'ar',
    'terms_url' => 'https://tawajah.ly/terms',
    'privacy_url' => 'https://tawajah.ly/privacy',
    'contact_url' => 'https://tawajah.ly/contact',
],
```

Extra stylesheets load after the bundled CSS. They can override the CSS variables (`--connex-bg`, `--connex-surface`, `--connex-text`, `--connex-brand`…) or any `.connex-*` class.

## Your own layout

Set `pages.layout` to a Blade layout that has `@yield('content')` and `@stack('connex-scripts')`. Your header, fonts and footer then wrap the bundled login and account pages.

## Your own page

Set `login.view` (and `account.view`) to your own view. The bundled JavaScript drives any markup that follows this contract:

| Element | Required |
| --- | --- |
| Container | `data-connex-login` with `data-script-url`, `data-otp-url`, `data-confirm-url` (use the `route()`s from the bundled view), `data-gateway-timeout`, `data-resend-template` |
| Phone form | `form[data-step="phone"]` with an `msisdn` input, an optional `accepted_terms` checkbox, and a submit button whose `id` is `{{ $targetElement }}` (watched by the anti-fraud script) |
| OTP form | `form[data-step="otp"]` with one input, a label `[data-otp-label]` (`data-template`, `data-sms-template`), `[data-resend]`, `[data-change-number]` |
| Feedback | `[data-loader]`, `[data-error]`, `[data-retry]` |

Variables passed to the view: `$targetElement`, `$otpLength`, `$requireTerms`, `$gatewayTimeout` and `$canCancel`. The easiest start is to copy the bundled view:

```bash
php artisan vendor:publish --tag=connex-views        # resources/views/vendor/connex
php artisan vendor:publish --tag=connex-translations # lang/vendor/connex
php artisan vendor:publish --tag=connex-assets       # resources/vendor/connex/{css,js}
```

Published views, translations and assets take precedence over the bundled ones, so a project can change only what it needs.

### Content-Security-Policy

The pages inline their CSS and JS. If your app sets a CSP, call `Vite::useCspNonce()` in a middleware. The pages put that nonce on every inline tag and on the injected anti-fraud script.

## Security

- The anti-fraud transaction id and the phone number live in the **server session**. The browser can't confirm an OTP for a number it didn't request, and the steps must run in order (otherwise 419).
- The service credentials and Connex token never reach the browser.
- OTP requests are limited per phone number (3/min) and per session (resend cooldown). OTP guesses are limited per phone number (5/min). The per-IP limits are generous because Libyan carriers put many subscribers behind one IP.
- The pages send `Cache-Control: no-store` and `noindex`.

## How users are created

The default `EloquentUserResolver` looks up `user.model` by `user.msisdn_column` (`phone`), using `user.format` (`international` 2189… or `national` 09…). If no user matches, it creates one with only that column and a random password. Two confirmations racing each other end up with the same user. **Existing users are never modified.**

To fill in more, bind your own resolver:

```php
$this->app->bind(ResolvesUsers::class, TawajahUserResolver::class);

final class TawajahUserResolver implements ResolvesUsers
{
    public function resolve(Msisdn $msisdn, LoginConfirmation $confirmation): Model&Authenticatable
    {
        return User::firstOrCreate(['phone' => $msisdn->international], ['password' => Str::password()]);
    }
}
```

## After login

`NeaMatrix\Connex\Login\Events\SubscriberLoggedIn` carries the user, the Connex confirmation, the device and the subscription:

```php
Event::listen(function (SubscriberLoggedIn $event) {
    if ($event->confirmation->isNewSubscriber()) {
        // welcome flow
    }
});
```

To change where the page goes after login, bind `NeaMatrix\Connex\Contracts\LoginResponder`.
