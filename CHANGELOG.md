# Changelog

## 2.0.0 (2026-11-23)

**Breaking:** the access states are renamed to the Connex UX terms, and `expired` now means something else.

| Old | New | Connex status | Expiry | Access | Button |
| --- | --- | --- | --- | --- | --- |
| `granted` | `active` | active | future | yes | none |
| `grace_period` | `inactive` | cancelled | future | yes, with a notice | `reactivate` |
| `expired` | `canceled` | cancelled | past | no (402) | `reactivate` |
| `insufficient_balance` | `expired` | active | past | no (402) | `top_up` |
| `none` | `none` | none / pending | | no (402) | `subscribe` |

- Enum cases: `Granted` → `Active`, `GracePeriod` → `Inactive`, `Expired` → `Canceled`, `InsufficientBalance` → `Expired`.
- New `SubscriptionAccess::message()` and `action()`. The subscription summary, the 402 reply of `connex.access` and the login activation step now include `message` and `action`.
- New notice texts (en/ar) in `connex::access`. Republished translations must rename their keys.
- The `insufficient_balance` **error code** from Connex OTP calls (messageCode 21) is unchanged.

## 1.6.2 (2026-11-22)

- Docs: [integration guide](docs/integration-guide.md), a step-by-step walkthrough into a Laravel project, with a go-live checklist and troubleshooting.

## 1.6.1 (2026-11-22)

- `cptpl_template` falls back to `20230925` when a published config predates `pages.compliance_template`, instead of leaving the input out.

## 1.6.0 (2026-11-22)

- Compliance Protect: the bundled layout now carries `<body class="cptpl_page">` and the hidden `cptpl_template` input. Without them Connex blocked `/login` as fraud (`messageCode 300`, `ft 8001`).
- `pages.compliance_template` (`CONNEX_COMPLIANCE_TEMPLATE`, default `20230925`) and the `connex::partials.compliance-template` partial for custom layouts.

## 1.5.0 (2026-11-18)

- Debug panel redesign: beside the page on wide screens, one card per page action, each Connex call with its full URL on Connex's server, method, HTTP status, `messageCode`, Connex's message, response time, and the raw request and reply (colour-coded).

## 1.4.1 (2026-11-18)

- Debug panel: our own reply to the page no longer prints the whole anti-fraud script, just its size.

## 1.4.0 (2026-11-18)

- Debug panel (`CONNEX_DEBUG=true`): the login and account pages show every raw request to Connex and its exact reply (HTTP status, `messageCode`, body). OTPs, passwords and tokens are hidden; the anti-fraud script is summarised.

## 1.3.0 (2026-11-18)

- Connex **Compliance Protect** markers on the bundled login page: `cptpl_logo`, `cptpl_service`, `cptpl_subscribe`, `cptpl_price`, `cptpl_terms`. The anti-fraud script looks for them; custom views should keep them.
- `pages.price`: the price lines Connex requires under the form (per locale).
- A `login` answered with `messageCode 01` inside `success` counts as "OTP sent", matching Ebtekar's own landing page.

## 1.2.1 (2026-11-18)

- `subscriberDetails()` returns `exists: false` for a number that never subscribed (Connex `messageCode 08`, HTTP 404, found on the real sandbox) instead of throwing.

## 1.2.0 (2026-11-18)

- Plans, OTP-confirmed plan changes and payment history: `GET /connex/api/subscription/plans`, `POST …/plan-change` (+ `/confirm`), `GET …/transactions`, and the same under `/connex/account` for the account page.
- The account page offers "Change package" when Connex lists more than one plan.
- `SubscriptionManager::plans()`, `requestPlanChange()`, `confirmPlanChange()`, `transactions()`.

## 1.1.0 (2026-11-13)

- `login.require_access`: lapsed subscribers activate (OTP) or re-check their balance on the login page before a code is handed back.
- Fake mode (`CONNEX_FAKE=true`): in-memory Connex seeded from stored subscriptions, for local and staging. Never active in production.

## 1.0.1 (2026-11-13)

- `connexAccess()` / `hasConnexAccess()` reuse an eager-loaded subscription and otherwise query, so they work in loops under `Model::preventLazyLoading()`.
- The test suite now runs with lazy loading prevented.

## 1.0.0 (2026-11-13)

- Connex API client covering every endpoint, with typed results and exceptions.
- Anti-fraud OTP login pages (Arabic and English) that are brandable and replaceable per project.
- Client login for mobile webviews and web frontends: single-use code (PKCE, state) exchanged for a Sanctum token, with redirect and popup modes.
- Subscription JSON API and one-time account tickets for showing the account page inside apps.
- Account page with OTP-confirmed activation and cancellation.
- Rate limits per phone number (carrier NAT safe), race-safe user and subscription creation, masked logs, CSP nonce support.
- Verified, idempotent webhooks with events.
- Local subscription and charge mirror, access rules and `connex.access` middleware.
- `Connex::fake()`, factories, `connex:check` and a local demo.
