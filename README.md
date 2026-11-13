# Laravel Connex

Laravel integration for **Connex**, Ebtekar's direct carrier billing (DCB) platform. Subscribers sign in with their phone number and an SMS code, and pay from their mobile balance (Libyana, Almadar).

The package covers everything the Connex integration guide requires:

- **Anti-fraud OTP login.** The DCBProtect script, phone step and OTP step, with resend, change number, rate limits and error messages.
- **The pages Connex requires.** A login page and an account page (status, expiry, activate, cancel), in Arabic and English with RTL, linking to your terms, privacy and contact pages.
- **Mobile apps.** A webview login that hands the app a single-use code, which the app exchanges for an API token.
- **Every Connex API endpoint** as a typed client, with typed results and typed exceptions.
- **Webhooks** for subscribe, unsubscribe, reactivate and renewal. They are verified, idempotent and stored, and each fires a Laravel event.
- **Content access rules.** `connex.access` middleware and `$user->hasConnexAccess()`.
- **Testing.** `Connex::fake()`, an in-memory Connex with assertions, plus a local demo app.

```php
$details = Connex::subscriberDetails('0920920110');

$details->status;            // SubscriptionStatus::Active
$details->expiresAt;         // CarbonImmutable
$details->access()->allowsAccess();
```

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- `laravel/sanctum`, only if the mobile app should receive Sanctum tokens

## Installation

```bash
composer require neamatrix/laravel-connex
php artisan vendor:publish --tag=connex-config
php artisan vendor:publish --tag=connex-migrations
php artisan migrate
```

Add the credentials Ebtekar gave you to `.env`:

```dotenv
CONNEX_BASE_URL=https://connextst.ebtekarcloud.com/external-api
CONNEX_EMAIL=service@example.com
CONNEX_PASSWORD=secret
CONNEX_WEBHOOK_SECRET=a-long-random-string
CONNEX_MOBILE_CALLBACK_URL=myapp://connex   # only for mobile apps
```

Add the trait to your user model:

```php
use NeaMatrix\Connex\Models\Concerns\HasConnexSubscription;

class User extends Authenticatable
{
    use HasConnexSubscription;
}
```

Check the connection:

```bash
php artisan connex:check --msisdn=0920920110
```

The subscriber's login page is now at `/connex/login` and their account page at `/connex/account`.

## Documentation

| Guide | What it covers |
| --- | --- |
| [How Connex works](docs/how-connex-works.md) | The platform, the flows, and how the package maps onto them |
| [Configuration](docs/configuration.md) | Every config option |
| [Login](docs/login.md) | Web login, customising the pages, how users are created |
| [Mobile apps](docs/mobile-apps.md) | Webview login and token handoff |
| [Subscriptions and access](docs/subscriptions.md) | The local mirror, access rules, middleware, activate and cancel |
| [Webhooks](docs/webhooks.md) | Registering URLs with Connex, security, events |
| [API client](docs/api-client.md) | Every endpoint, results and exceptions |
| [Testing](docs/testing.md) | `Connex::fake()` and the local demo |
| [Architecture](docs/architecture.md) | How the code is organised and where to extend it |

## Local demo

```bash
composer install
composer demo
```

This opens `http://127.0.0.1:8000/connex/login` against an in-memory Connex. Any Libyan mobile number works, and the code is `1234`.

## Development

```bash
composer test      # Pest
composer analyse   # PHPStan level 8
composer format    # Pint
composer check     # all three
```

## License

MIT
