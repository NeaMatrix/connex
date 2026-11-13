# How Connex works

Connex is Ebtekar's direct carrier billing platform. A "service" (your app) is registered on Connex with one or more subscription packages. Subscribers prove they own a phone number with a 4-digit SMS code, and Connex charges their mobile balance each period.

## The flows

### Login (subscribe)

1. The landing page loads. Your server calls `auth-login` to get a service token (valid for 1 hour), then calls `protected-script`.
2. The browser runs the returned **DCBProtect** anti-fraud script. The script watches the page's submit button and fires `gateway-load` when it is ready.
3. The subscriber enters their number. Your server calls `login` with the `transaction_identify` returned by `protected-script`, and Connex sends the OTP.
4. The subscriber enters the OTP. Your server calls `login-confirm`. New subscribers are created and may be charged an initial bill.

Connex may answer step 3 with `messageCode` **300** (fraud), **05** (an OTP was requested too recently) or **02** with a list of packages (more than one plan is configured).

### Account

Connex requires a profile page that shows the number, status (active or cancel) and expiration date, with **Activate subscription** or **Cancel subscription**. Each of those buttons is confirmed by OTP:

- `subscription-activation` → `subscription-activation-confirm`
- `unsubscribe` → `unsubscribe-confirm`

### Content rules

| Status | Expired? | Access | Message |
| --- | --- | --- | --- |
| active | no | yes | — |
| active | yes | no | "Your balance is insufficient" (the renewal charge failed) |
| cancel | no | yes, until expiry | prompt to activate |
| cancel | yes | no | "Please renew your subscription" |

### Notifications (webhooks)

Connex calls your server when something happens outside your website: an SMS subscription, a cancellation, a reactivation, or a **renewal** charge.

### Mobile apps

The guide requires apps to show the login, OTP and profile pages as **webviews**, because the anti-fraud script must run on a web page.

## How the package maps onto this

| Connex requirement | Package |
| --- | --- |
| Token from `auth-login`, sent as Bearer | `AccessTokenProvider` caches it until shortly before it expires, and refreshes once on a 401 |
| Anti-fraud script and `transaction_identify` | `GET /connex/login/script`. The transaction id stays in the server session. |
| Phone and OTP landing page, Arabic option, terms/privacy/contact links | `/connex/login` with `?lang=ar\|en` |
| Profile page with activate and cancel | `/connex/account` |
| Content rules | `SubscriptionAccess`, the `connex.access` middleware and `HasConnexSubscription` |
| Webview pages for apps | `/connex/login?device=android\|ios`, followed by the handoff code exchange |
| Notifications | `POST /connex/webhooks/{type}?token=…` |
| All other endpoints | `Connex::` facade / `ConnexClient` |

## Open questions to raise with Ebtekar

- **Plan selection.** When `login` returns `02` with a plan list, the documentation doesn't say how to send the chosen plan. The package raises `PlanSelectionRequired` (shown as "Please choose a package"). Ask Ebtekar to configure a default package for the service, or to document the parameter.
- **Webhook authentication.** Notifications aren't signed. The package requires a secret token in the URL, and can also restrict calls to Connex's IP addresses. Ask Ebtekar for those IP addresses.
- **`auth-login` encoding.** The Postman collection sends form-data, and the package does the same.
