# Configuration

Publish the config with `php artisan vendor:publish --tag=connex-config`. Every option is in `config/connex.php`.

## Connection

| Key | Env | Default | |
| --- | --- | --- | --- |
| `base_url` | `CONNEX_BASE_URL` | sandbox | Ebtekar gives you the production host |
| `credentials.email` / `.password` | `CONNEX_EMAIL` / `CONNEX_PASSWORD` | — | Service account |
| `http.timeout` / `.connect_timeout` | `CONNEX_TIMEOUT` / `CONNEX_CONNECT_TIMEOUT` | 15 / 5 s | |
| `cache_store` | `CONNEX_CACHE_STORE` | default store | Holds the API token and the handoff codes. Use a shared store (Redis, database) when running more than one server. |
| `script_owner` | `CONNEX_DCB_SCRIPT_OWNER` | — | Your Evina account, only if you already integrate Evina directly (sent as `dcb_script_owner`) |
| `timezone` | `CONNEX_TIMEZONE` | `Africa/Tripoli` | Connex dates have no offset. They are converted to your app timezone. |
| `logging.channel` | `CONNEX_LOG_CHANNEL` | off | Logs every call. OTPs and passwords are never logged. |

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

## Pages

| Key | Default | |
| --- | --- | --- |
| `login.enabled` / `account.enabled` | `true` | Turn off the bundled routes |
| `login.prefix` | `connex` | URL prefix for login, account and handoff |
| `login.redirect_to` | `/` | Where web users go after login (an intended URL wins) |
| `login.guard` | default | Session guard used for web logins |
| `login.mobile_callback_url` | — | App deep link for webview logins |
| `login.handoff_ttl` | 120 s | Lifetime of a handoff code |
| `login.target_element` | `connex-submit` | id of the submit button the anti-fraud script watches |
| `login.gateway_timeout_ms` | 15000 | Enable the button anyway if `gateway-load` never fires |
| `login.resend_cooldown` | 60 s | Minimum time between two OTP requests in one session |
| `login.throttle.*` | 20 / 5 / 10 | Requests per minute per IP for script / OTP / confirm |
| `account.middleware` | `['web', 'auth']` | |
| `account.logout_url` | — | Shows a logout button that POSTs here |
| `pages.brand_name` / `brand_color` | `APP_NAME` / `#c8333b` | |
| `pages.default_locale` | `ar` | |
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
