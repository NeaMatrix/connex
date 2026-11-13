# Mobile apps

Connex requires apps to show the login, OTP and profile pages as webviews. The package logs the subscriber in inside the webview, then hands the app a **single-use code**. The app exchanges the code for an API token over HTTPS, so the token never appears in a URL.

```
App                         Webview                          Your server
 │ open webview ──────────▶ /connex/login?device=android
 │                          phone → OTP ───────────────────▶ confirm with Connex
 │ ◀──── redirect myapp://connex?code=XXXX (64 chars, 2 min, single use)
 │ POST /connex/api/handoff {"code": "XXXX"} ───────────────▶
 │ ◀──── {"token": "1|…", "token_type": "Bearer", "expires_at": null}
```

## Setup

1. Set `CONNEX_MOBILE_CALLBACK_URL` to a URL the app intercepts, either a custom scheme (`myapp://connex`) or a universal/app link.
2. Make the user model issue tokens. The default `SanctumTokenIssuer` needs `Laravel\Sanctum\HasApiTokens` on the model. To issue tokens another way, bind `NeaMatrix\Connex\Contracts\IssuesTokens`.
3. In the app:
   - Open `https://your-app/connex/login?device=android` (or `ios`, plus `&lang=ar`).
   - Intercept navigation to the callback URL and read `code`.
   - `POST /connex/api/handoff` with `{"code": "..."}` and store the returned token.

## The account page in the app

A webview login doesn't create a web session (the app gets a token instead), so build the account screen natively. It can call these JSON endpoints, which require auth:

| Method | URL | Body |
| --- | --- | --- |
| POST | `/connex/account/activation` | — |
| POST | `/connex/account/activation/confirm` | `otp` |
| POST | `/connex/account/cancellation` | — |
| POST | `/connex/account/cancellation/confirm` | `otp` |

For API-token apps, set `account.middleware` to `['api', 'auth:sanctum']`, or call `SubscriptionManager` from your own API controllers.
