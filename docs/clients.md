# Signing in apps and frontends

The Connex login page is the only login. Mobile apps open it in a webview, and web frontends redirect to it or open it in a popup. When the subscriber finishes, the page sends them back to the client with a **single-use code**. The client exchanges the code for a **Sanctum token** and uses that token on your API.

This is the OAuth 2.0 authorization-code flow with PKCE, the same flow "Sign in with Google" uses. The token never appears in a URL, browser history or logs.

```
Client (app / frontend)                    Your Laravel app (this package)
  │ 1. open /connex/login?client=…&redirect_uri=…&state=…&code_challenge=…
  │                                        anti-fraud script → phone → OTP → Connex
  │ 2. ◀── redirect_uri?code=…&state=…     (64 chars, single use, 2 minutes)
  │ 3. POST /connex/api/token {client, code, redirect_uri, code_verifier}
  │ 4. ◀── {"token": "1|…", "token_type": "Bearer", "user": {…}, "subscription": {…}}
  │ 5. Authorization: Bearer 1|…  →  your own API routes (auth:sanctum)
```

## 1. Register the clients

```php
// config/connex.php
'clients' => [
    'mobile' => [
        'redirect_uris' => ['tawajah://auth/connex'],
        'pkce' => true,
    ],
    'web' => [
        'redirect_uris' => ['https://app.tawajah.ly/auth/connex/callback'],
        'pkce' => true,
    ],
],
```

Redirect URIs must match **exactly**. A login link with an unknown client or an unlisted URI shows an error page and never redirects. Keep `pkce` on for every public client (mobile apps and browser frontends).

Add `HasApiTokens` (Laravel Sanctum) to the user model.

## 2. Open the login page

| Parameter | |
| --- | --- |
| `client` | id from `connex.clients` |
| `redirect_uri` | one of the client's URIs |
| `state` | random value; check it when the code comes back (CSRF protection for the client) |
| `code_challenge` | `base64url(sha256(code_verifier))`. The verifier is 43–128 random characters the client keeps. |
| `code_challenge_method` | `S256` |
| `device` | `android`, `ios` or `web` (sent to Connex) |
| `response_mode` | `query` (default, redirect) or `web_message` (popup, see below) |
| `lang` | `ar` or `en` |
| `campaign_id`, `tracking_id` | optional, forwarded to Connex |

If the subscriber taps **Cancel**, the page returns `redirect_uri?error=access_denied&state=…`.

## 3. Exchange the code

```http
POST /connex/api/token
Content-Type: application/json

{"client": "mobile", "code": "…", "redirect_uri": "tawajah://auth/connex", "code_verifier": "…"}
```

```json
{
  "token": "1|cT3W3iFwDzezNQW2mK85W8ROz8H7NlNUCB7XC1wN3807ac1d",
  "token_type": "Bearer",
  "expires_at": null,
  "is_new_subscriber": true,
  "user": { "id": 1 },
  "subscription": {
    "msisdn": "218920920110", "status": "active", "plan": null,
    "expires_at": "2026-11-14T00:13:57+00:00", "access": "granted", "allows_access": true
  }
}
```

A code works once, only for the client and redirect it was issued to, and only with the right verifier. Anything else returns `400 {"error": "invalid_grant"}`. Use `is_new_subscriber` to show onboarding.

**Token options** (`connex.tokens`): `name` (tokens are named `{name}-{device}`), `abilities`, `expires_in` (minutes) and `code_ttl`.

**Your own response.** To return your API's user resource, bind `NeaMatrix\Connex\Contracts\BuildsTokenResponse`:

```php
final class TawajahTokenResponse implements BuildsTokenResponse
{
    public function build(Authenticatable $user, IssuedToken $token, LoginGrant $grant): array
    {
        return $token->toArray() + [
            'is_new_subscriber' => $grant->isNewSubscriber,
            'user' => UserResource::make($user->loadForMe())->resolve(),
        ];
    }
}
```

To issue something other than Sanctum tokens, bind `NeaMatrix\Connex\Contracts\IssuesTokens`.

## Mobile apps (webview)

1. Create a verifier and `state`, then open `https://api.example.com/connex/login?client=mobile&redirect_uri=tawajah%3A%2F%2Fauth%2Fconnex&device=android&…` in a webview.
2. Intercept navigation to `tawajah://auth/connex`, check `state`, read `code` (or `error`).
3. Close the webview and `POST /connex/api/token`.

Flutter (`webview_flutter`):

```dart
NavigationDelegate(onNavigationRequest: (request) {
  final uri = Uri.parse(request.url);
  if (uri.scheme == 'tawajah') {
    Navigator.pop(context, uri.queryParameters); // {code, state} or {error, state}
    return NavigationDecision.prevent;
  }
  return NavigationDecision.navigate;
});
```

## Web frontends

**Redirect.** Send the browser to the login URL. Your callback page reads `code` and `state` and calls `/connex/api/token`. If the frontend runs on another domain, allow it in `config/cors.php`:

```php
'paths' => ['api/*', 'connex/api/*'],
'allowed_origins' => ['https://app.tawajah.ly'],
```

**Popup.** Add `response_mode=web_message` (needs an `https://` redirect URI). Instead of navigating, the page posts `{type: "connex:login", code, state}` to `window.opener` at the redirect URI's origin, then closes:

```js
window.addEventListener('message', (event) => {
  if (event.origin === 'https://app.tawajah.ly' && event.data?.type === 'connex:login') {
    exchange(event.data.code, event.data.state);
  }
});
```

`workbench/resources/views/frontend.blade.php` is a complete working example (PKCE, state check, redirect and popup). Run it with `composer demo`.

## The account page (Connex requirement)

Apps have the subscriber's token, not a browser session. To show the Connex account page in a webview:

```http
POST /connex/api/account-link          (Authorization: Bearer …)
{"device": "android", "lang": "ar"}

→ {"url": "https://api.example.com/connex/account/enter?ticket=…", "expires_in": 120}
```

Open `url` in a webview. The single-use ticket signs the webview in and shows the page.

Or build the screen natively with the JSON API, which requires a Sanctum token:

| Method | URL | Body |
| --- | --- | --- |
| GET | `/connex/api/subscription` | — |
| POST | `/connex/api/subscription/activation` | — |
| POST | `/connex/api/subscription/activation/confirm` | `otp`, `device` |
| POST | `/connex/api/subscription/cancellation` | — |
| POST | `/connex/api/subscription/cancellation/confirm` | `otp`, `device` |

## Same-domain session login

A Blade or Inertia app served from the same Laravel app can link to `/connex/login` without `client`. The page signs the visitor into `login.guard` and redirects to the intended URL or `login.redirect_to`. Set `login.allow_session_login` to `false` to accept client logins only.
