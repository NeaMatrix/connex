# Laravel Connex

Laravel integration for **Connex**, Ebtekar's direct carrier billing (DCB) platform. Subscribers sign in with their phone number and an SMS code, and pay from their mobile balance (Libyana, Almadar).

The package covers everything the Connex integration guide requires:

- **Anti-fraud OTP login.** The DCBProtect script, phone step and OTP step, with resend, change number, rate limits and error messages.
- **The pages Connex requires.** A login page and an account page (status, expiry, activate, cancel), in Arabic and English with RTL, linking to your terms, privacy and contact pages.
- **One login for every client.** Mobile apps (webview) and web frontends (redirect or popup) get back a single-use code (OAuth-style, with PKCE) and exchange it for a **Sanctum token** to use on your API.
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
- `laravel/sanctum`, to issue API tokens to apps and frontends

## Installation

```bash
composer require neamatrix/laravel-connex
composer require laravel/sanctum   # if not installed
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
```

Register your apps and frontends in `config/connex.php` (see [Apps and frontends](docs/clients.md)):

```php
'clients' => [
    'mobile' => ['redirect_uris' => ['myapp://auth/connex'], 'pkce' => true],
    'web' => ['redirect_uris' => ['https://app.example.com/auth/connex/callback'], 'pkce' => true],
],
```

Add the traits to your user model:

```php
use Laravel\Sanctum\HasApiTokens;
use NeaMatrix\Connex\Models\Concerns\HasConnexSubscription;

class User extends Authenticatable
{
    use HasApiTokens, HasConnexSubscription;
}
```

Check the connection:

```bash
php artisan connex:check --msisdn=0920920110
```

Clients open `/connex/login?client=…`. The subscriber's account page is at `/connex/account`.

## Documentation

| Guide | What it covers |
| --- | --- |
| [How Connex works](docs/how-connex-works.md) | The platform, the flows, and how the package maps onto them |
| [Configuration](docs/configuration.md) | Every config option |
| [Apps and frontends](docs/clients.md) | Webview and frontend login, code → Sanctum token, account page in apps |
| [Login page](docs/login.md) | Customising and branding the pages per project, how users are created |
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

This opens `http://127.0.0.1:8000/demo`, a sample frontend that signs in through the package and calls a Sanctum-protected API, all against an in-memory Connex. Any Libyan mobile number works, and the code is `1234`.

## Development

```bash
composer test      # Pest
composer analyse   # PHPStan level 8
composer format    # Pint
composer check     # all three
```

## License

MIT
