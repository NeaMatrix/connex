# Changelog

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
