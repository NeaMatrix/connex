# Changelog

## 1.0.0 (unreleased)

- Connex API client covering every endpoint, with typed results and exceptions.
- Anti-fraud OTP login pages (Arabic and English) that are brandable and replaceable per project.
- Client login for mobile webviews and web frontends: single-use code (PKCE, state) exchanged for a Sanctum token, with redirect and popup modes.
- Subscription JSON API and one-time account tickets for showing the account page inside apps.
- Account page with OTP-confirmed activation and cancellation.
- Rate limits per phone number (carrier NAT safe), race-safe user and subscription creation, masked logs, CSP nonce support.
- Verified, idempotent webhooks with events.
- Local subscription and charge mirror, access rules and `connex.access` middleware.
- `Connex::fake()`, factories, `connex:check` and a local demo.
