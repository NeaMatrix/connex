# Integrating the package into a Laravel project

A step-by-step walkthrough, based on how Tawajah went live with it. Follow the steps in order; each one links to the detailed guide.

## 1. What you need from Ebtekar

- The Connex **base URL** (sandbox first, then production) and a **service account** (email and password).
- The **domain** of your landing page, as registered with Connex. The anti-fraud script only trusts pages served from it.
- Your **logo**, **service name** and **price text**, exactly as approved. The anti-fraud script checks the page for them.
- The **Compliance Protect template id**, if they give you one other than `20230925`.
- If Connex only accepts your calls from whitelisted IPs: a server with a whitelisted IP, or a proxy on one.

## 2. Install

```bash
composer require neamatrix/laravel-connex
composer require laravel/sanctum   # if not installed
php artisan vendor:publish --tag=connex-config
php artisan vendor:publish --tag=connex-migrations
php artisan migrate
```

Add the traits to the user model:

```php
use Laravel\Sanctum\HasApiTokens;
use NeaMatrix\Connex\Models\Concerns\HasConnexSubscription;

class User extends Authenticatable
{
    use HasApiTokens, HasConnexSubscription;
}
```

## 3. Environment

```dotenv
CONNEX_BASE_URL=https://connextst.ebtekarcloud.com/external-api
CONNEX_EMAIL=service@example.com
CONNEX_PASSWORD=secret
CONNEX_WEBHOOK_SECRET=a-long-random-string
CONNEX_COMPLIANCE_TEMPLATE=20230925

# Local only: shows every Connex request and reply under the page. Never in production.
CONNEX_DEBUG=true
# Local only: an in-memory Connex, any number works, the code is CONNEX_FAKE_OTP.
CONNEX_FAKE=false
```

If you call Connex through a proxy, `CONNEX_BASE_URL` is the proxy's URL. See [step 9](#9-calling-connex-through-a-proxy).

Check the connection:

```bash
php artisan connex:check --msisdn=0920920110
```

## 4. Configure `config/connex.php`

The settings Tawajah changed from the defaults:

```php
'login' => [
    'target_element' => 'cta_button',   // the subscribe button id; Connex's guide and pages use cta_button
    'require_terms' => false,           // true shows a checkbox; false shows the terms sentence instead
    'require_access' => true,           // only let in numbers with an active subscription
    'redirect_to' => '/connex/account', // where same-domain logins land
],

'pages' => [
    'brand_name' => 'Tawajah - توجه',
    'brand_color' => '#2563eb',
    'logo_url' => '/images/tawajah-logo.svg',
    'price' => [
        'الفترة المجانية يوم ومن ثم 0.5 دينار يومياً لمستخدمي ليبيانا',
    ],
    'compliance_template' => env('CONNEX_COMPLIANCE_TEMPLATE', '20230925'),
    'terms_url' => env('CONNEX_TERMS_URL'),
    'privacy_url' => env('CONNEX_PRIVACY_URL'),
    'contact_url' => env('CONNEX_CONTACT_URL'),
],
```

> **After every package upgrade, compare your published config with the package's.** Laravel does not merge new nested keys (such as `pages.compliance_template`) into a published config. Check the [changelog](../CHANGELOG.md) for new keys and copy them over.

Every option is described in [Configuration](configuration.md).

## 5. Register your apps and frontends

Mobile apps open the login page in a webview; web frontends redirect to it. Both get a single-use code back and exchange it for a Sanctum token. Keep the redirect URIs in `.env` so each environment has its own:

```php
'clients' => [
    'mobile' => [
        'redirect_uris' => array_values(array_filter(explode(',', (string) env('CONNEX_MOBILE_REDIRECT_URIS', 'myapp://auth/connex')))),
        'pkce' => true,
    ],
    'web' => [
        'redirect_uris' => array_values(array_filter(explode(',', (string) env('CONNEX_WEB_REDIRECT_URIS', '')))),
        'pkce' => true,
    ],
],
```

The full flow, with code for apps and frontends, is in [Apps and frontends](clients.md).

## 6. The login page

The bundled page works as is. To match an approved landing page, use your own layout and view:

```php
'login' => ['view' => 'connex.login'],
'pages' => ['layout' => 'connex.layout'],
```

Start from the package's views (`resources/views/login.blade.php` and `layouts/page.blade.php`) and keep their `data-*` hooks.

**The page must carry every Compliance Protect marker.** If any is missing, Connex blocks `/login` as fraud (`messageCode 300`, `ft 8001`), even though every request is correct:

```blade
<body class="cptpl_page">
    @include('connex::partials.compliance-template')   {{-- the hidden cptpl_template input --}}

    <img src="/images/logo.svg" alt="Logo" class="cptpl_logo">
    <span class="cptpl_service">Service name</span>

    {{-- the subscribe button: id = login.target_element --}}
    <button id="{{ $targetElement }}" type="submit" class="cptpl_subscribe">Subscribe</button>

    <div class="cptpl_price">…</div>
    <p class="cptpl_terms">…</p>
</body>
```

**Serve the page on the domain registered with Connex.** To make it the home page, as Tawajah does:

```php
// routes/web.php
use NeaMatrix\Connex\Login\Controllers\LoginPageController;

Route::get('/', LoginPageController::class)->name('home');
```

More on views, branding and CSP is in [Login page](login.md).

## 7. Protect your API

```php
Route::middleware(['auth:sanctum', 'connex.access'])->group(function () {
    // routes that need an active (or still-paid) subscription
});
```

Without a subscription, `connex.access` answers JSON requests with `402` and redirects the others. Tawajah wraps it in its own middleware with a kill switch (`'require_subscription' => (bool) env('CONNEX_REQUIRE_SUBSCRIPTION', true)` in `config/connex.php`), so the app can still be tested if Connex is down:

```php
class RequireConnexSubscription
{
    public function __construct(private EnsureConnexAccess $connexAccess) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('connex.require_subscription')) {
            return $next($request);
        }

        return $this->connexAccess->handle($request, $next);
    }
}
```

See [Subscriptions and access](subscriptions.md).

## 8. Webhooks

Give Connex these URLs. They are verified and idempotent, and each one fires a Laravel event:

```
https://your-app/connex/webhooks/{subscribe|unsubscribe|reactivate|renewal}?token=CONNEX_WEBHOOK_SECRET
```

See [Webhooks](webhooks.md).

## 9. Calling Connex through a proxy

If Connex only accepts your server's calls from a whitelisted IP, point `CONNEX_BASE_URL` at a proxy that forwards to Connex. Some proxies wrap Connex's errors, for example `{"message": "External API error", "error": {"messageCode": "300", …}}`. The package reads a top-level `messageCode`, so a fraud block would show as "service unavailable". Tawajah adds an HTTP client middleware that hands the inner reply to the package:

```php
// AppServiceProvider::boot()
Http::globalMiddleware(new UnwrapConnexProxyErrors);
```

It only touches requests to `connex.base_url`, and only replies that have no top-level `messageCode` but do have `error.messageCode`.

## 10. Optional customisations

All of these are container bindings in `AppServiceProvider::register()`.

**Shape the token reply like your own login.** Then the mobile app handles a Connex login exactly like a password login:

```php
$this->app->bind(BuildsTokenResponse::class, ConnexTokenResponse::class);

class ConnexTokenResponse implements BuildsTokenResponse
{
    public function build(Authenticatable $user, IssuedToken $token, LoginGrant $grant): array
    {
        return AuthTokenResource::make(['token' => $token->token, 'user' => $user])
            ->response()->getData(true);
    }
}
```

**Always send `device_type: web`.** The anti-fraud script runs in a web page even when an app opens it, and Tawajah's web flow is what Connex accepted. Extend `HttpConnexClient`, pass `DeviceType::Web` to `parent::login()`, `confirmLogin()` and `confirmActivation()`, then swap it in:

```php
$this->app->extend(ConnexClient::class, fn (ConnexClient $client, Application $app): ConnexClient => $client instanceof HttpConnexClient
    ? $app->make(WebDeviceConnexClient::class)
    : $client);
```

The `instanceof` check keeps `Connex::fake()` working in tests.

## 11. Test

```php
use NeaMatrix\Connex\Facades\Connex;

$connex = Connex::fake();
// … run the flow …
$connex->assertCalled('login');
```

Assert the login page carries every Compliance Protect marker, so a view change can't silently break it. See [Testing](testing.md).

## 12. Go-live checklist

- [ ] Production `CONNEX_BASE_URL` and credentials; `CONNEX_DEBUG` and `CONNEX_FAKE` off.
- [ ] The login page is served on the domain registered with Connex.
- [ ] Logo, service name, price and terms match what Connex approved.
- [ ] All seven markers are on the page: `cptpl_page`, `cptpl_template`, `cptpl_logo`, `cptpl_service`, `cptpl_subscribe`, `cptpl_price`, `cptpl_terms`.
- [ ] The subscribe button's id equals `login.target_element`.
- [ ] Webhook URLs are registered with Connex, and the secret is set.
- [ ] Every client's redirect URI is registered in `clients`.
- [ ] One real sign-in from a phone on mobile data, end to end.

## Troubleshooting

| Symptom | Likely cause |
| --- | --- |
| `messageCode 300`, `ft 8001` ("This operation is not allowed") | A Compliance Protect marker is missing (most often `cptpl_page` or `cptpl_template`), the page is on an unregistered domain, or the button id differs from `target_element`. Compare the page with one Connex already accepts. |
| Fraud blocks shown as "service unavailable" | A proxy wraps Connex's error. See [step 9](#9-calling-connex-through-a-proxy). |
| The submit button never enables | The anti-fraud script did not load. Check `CONNEX_DEBUG=true` output and the browser console (CSP, ad blockers). |
| A new config option has no effect | Your published `config/connex.php` predates it. Copy the key over. |
| `auth-login` fails | Wrong `CONNEX_EMAIL` or `CONNEX_PASSWORD` for this base URL. Sandbox and production accounts differ. |
