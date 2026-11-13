# Login

Send subscribers to `route('connex.login')`. The page:

1. Shows a loader, fetches the anti-fraud script from your server, runs it, and waits for `gateway-load`.
2. Asks for the phone number and acceptance of the terms.
3. Asks for the OTP, with a resend countdown and a "Change number" link.
4. On success, signs the user in and redirects to `login.redirect_to`.

Every error (wrong code, fraud, rate limit, insufficient balance, Connex down) is shown in the subscriber's language.

Query parameters on `/connex/login`:

| Parameter | |
| --- | --- |
| `lang=ar\|en` | Language, remembered in the session |
| `device=android\|ios` | Webview login from an app (see [Mobile apps](mobile-apps.md)) |
| `campaign_id`, `tracking_id` | Passed to Connex on confirmation, for campaign tracking |

## Security

- The transaction id and the phone number live in the **server session**. The browser can't confirm an OTP for a number it didn't request.
- The service credentials and token never reach the browser.
- Each step is rate limited per IP, and OTP requests have a cooldown per session.
- The steps must run in order. Skipping one returns 419 "session expired".

## Customising the pages

```bash
php artisan vendor:publish --tag=connex-views        # resources/views/vendor/connex
php artisan vendor:publish --tag=connex-translations # lang/vendor/connex
php artisan vendor:publish --tag=connex-assets       # resources/vendor/connex/{css,js}
```

Or point `login.view` / `account.view` at your own Blade views. The scripts rely on:

- `[data-connex-login]` on the container, with the `data-*-url` attributes from the bundled view
- `form[data-step="phone"]` containing an `msisdn` input, an `accepted_terms` checkbox and a submit button whose `id` is `login.target_element`
- `form[data-step="otp"]` with one input, `[data-otp-label]`, `[data-resend]` and `[data-change-number]`
- `[data-loader]`, `[data-error]` and `[data-retry]`

## How users are created

The default `EloquentUserResolver` looks up `user.model` by `user.msisdn_column`. If no user matches, it creates one with only that column and a random password. **Existing users are never modified.**

To do something else, such as filling in a profile or matching by another column, bind your own resolver:

```php
use NeaMatrix\Connex\Contracts\ResolvesUsers;

$this->app->bind(ResolvesUsers::class, MyUserResolver::class);
```

```php
final class MyUserResolver implements ResolvesUsers
{
    public function resolve(Msisdn $msisdn, LoginConfirmation $confirmation): Model&Authenticatable
    {
        return User::firstOrCreate(['phone' => $msisdn->national()], ['type' => 'learner']);
    }
}
```

## After login

`NeaMatrix\Connex\Login\Events\SubscriberLoggedIn` carries the user, the Connex confirmation, the device and the subscription:

```php
Event::listen(function (SubscriberLoggedIn $event) {
    if ($event->confirmation->isNewSubscriber()) {
        // welcome slides, onboarding...
    }
});
```

To replace what happens after login entirely, for example a different redirect per user, bind `NeaMatrix\Connex\Contracts\LoginResponder`.
