# Configuration

Publish the config with `php artisan vendor:publish --tag=connex-config`. Every option is in `config/connex.php`.

## Connection

| Key | Env | Default | |
| --- | --- | --- | --- |
| `base_url` | `CONNEX_BASE_URL` | — (required) | Sandbox: `https://connextst.ebtekarcloud.com/external-api` |
| `credentials.email` / `.password` | `CONNEX_EMAIL` / `CONNEX_PASSWORD` | — | Service account |
| `http.timeout` / `.connect_timeout` | `CONNEX_TIMEOUT` / `CONNEX_CONNECT_TIMEOUT` | 15 / 5 s | |
| `cache_store` | `CONNEX_CACHE_STORE` | default store | Holds the API token, login codes and account tickets. Use a shared store (Redis, database) when running more than one server. |
| `script_owner` | `CONNEX_DCB_SCRIPT_OWNER` | — | Your Evina account, only if you already integrate Evina directly (sent as `dcb_script_owner`) |
| `timezone` | `CONNEX_TIMEZONE` | `Africa/Tripoli` | Connex dates have no offset. They are converted to your app timezone. |
| `logging.channel` | `CONNEX_LOG_CHANNEL` | off | Logs every call. OTPs and passwords are never logged. |
| `fake.enabled` / `fake.otp` | `CONNEX_FAKE` / `CONNEX_FAKE_OTP` | off / `1234` | In-memory Connex for local and staging; never active in production |

## Phone numbers

```php
'msisdn' => [
    'country_code' => '218',
    'national_length' => 9,
    'mobile_prefixes' => ['91', '92', '93', '94', '95'],
],
```

Input such as `0920920110`, `920920110`, `+218 92 092 0110` and `00218920920110` all becomes `218920920110`.

## Users

```php
'user' => [
    'model' => App\Models\User::class,
    'msisdn_column' => 'phone',
    'format' => 'international',   // or 'national' if you store 09XXXXXXXX
    'password_column' => 'password', // null if your users have no password
],
```

To control how users are created, see [Login](login.md#how-users-are-created).

## Clients and tokens

See [Apps and frontends](clients.md).

| Key | Default | |
| --- | --- | --- |
| `clients.{id}.redirect_uris` | — | Exact URIs the login may return to |
| `clients.{id}.pkce` | `true` | Require a PKCE code_challenge |
| `tokens.name` | `connex` | Tokens are named `{name}-{device}` |
| `tokens.abilities` | `['*']` | Sanctum abilities |
| `tokens.expires_in` | `null` | Minutes; `null` uses Sanctum's setting |
| `tokens.code_ttl` | 120 s | Lifetime of a login code |

## Login page

| Key | Default | |
| --- | --- | --- |
| `login.enabled` | `true` | |
| `login.prefix` | `connex` | URL prefix for every package route except webhooks |
| `login.middleware` / `api_middleware` | `['web']` / `['api']` | |
| `login.view` | `connex::login` | Your own view (see [Login page](login.md)) |
| `login.require_terms` | `true` | Terms checkbox |
| `login.require_access` | `false` | Ask lapsed subscribers to activate before handing back the login |
| `login.allow_session_login` | `true` | Allow `/connex/login` without a client (same-domain session login) |
| `login.guard` / `redirect_to` | `web` / `/` | For session logins |
| `login.target_element` | `connex-submit` | id of the button the anti-fraud script watches |
| `login.gateway_timeout_ms` | 15000 | Enable the button anyway if `gateway-load` never fires |
| `login.resend_cooldown` | 60 s | Between OTP requests in one session |
| `login.throttle.*` | see file | Per-minute limits per phone number, per user and per IP |

## Account

| Key | Default | |
| --- | --- | --- |
| `account.enabled` | `true` | Account page, subscription API and account tickets |
| `account.middleware` | `['web', 'auth']` | Account page |
| `account.api_middleware` | `['api', 'auth:sanctum']` | `/connex/api/subscription*` and `/connex/api/account-link` |
| `account.guard` | `web` | Session guard an account ticket signs into |
| `account.ticket_ttl` | 120 s | |
| `account.view` / `logout_url` | `connex::account` / — | |

## Pages

| Key | Default | |
| --- | --- | --- |
| `pages.brand_name` / `brand_color` / `logo_url` | `APP_NAME` / `#c8333b` / — | |
| `pages.stylesheets` | `[]` | Extra CSS URLs loaded after the bundled CSS |
| `pages.layout` | `connex::layouts.page` | Your own layout |
| `pages.locales` / `default_locale` | `['ar', 'en']` / `ar` | |
| `pages.*_url` | — | Header links: home, about, terms, privacy, contact |

## Storage and webhooks

| Key | Default | |
| --- | --- | --- |
| `subscriptions.store` | `true` | Keep the `connex_subscriptions` / `connex_charges` mirror. If `false`, only events are fired. |
| `models.subscription` / `models.charge` | package models | Extend them to add relations |
| `webhooks.enabled` | `true` | |
| `webhooks.prefix` | `connex/webhooks` | |
| `webhooks.secret` | `CONNEX_WEBHOOK_SECRET` | Required. Webhooks answer 503 until it is set. |
| `webhooks.allowed_ips` | `CONNEX_WEBHOOK_IPS` | Comma-separated IPs or CIDR ranges |
