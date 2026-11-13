# Architecture

```
src/
├── Client/          HTTP layer: Transport (auth, retry, logging), AccessTokenProvider,
│                    ResponseParser, HttpConnexClient (one method per endpoint)
├── Contracts/       ConnexClient, ResolvesUsers, LoginResponder, IssuesTokens
├── Data/            Read-only results (OtpChallenge, LoginConfirmation, SubscriberDetails…)
├── Enums/           MessageCode, Endpoint, DeviceType, SubscriptionStatus, SubscriptionAccess…
├── Exceptions/      ConnexException hierarchy + ExceptionFactory (code + endpoint → exception)
├── Support/         Msisdn value object and normaliser, typed Payload reader, assets
├── Login/           LoginFlow (the three steps), LoginSession (server-side state), controllers
│   └── Clients/     Registered clients and validated authorization requests (redirect_uri, state, PKCE)
├── Auth/            Login codes (one-time, atomic), PKCE, token exchange, Sanctum issuer, default resolver
├── Account/         Account page, subscription JSON API, webview account tickets
├── Subscriptions/   SubscriptionStore (the mirror), SubscriptionManager (owner-level actions)
├── Webhooks/        Notifications (validated DTOs), processor, controller, events, verification
├── Models/          ConnexSubscription, ConnexCharge, HasConnexSubscription
├── Http/            Middleware (connex.access, locale) and the shared error response
└── Testing/         FakeConnexClient
```

## Principles

- **One request path.** Every call goes through `Transport::send()`, which handles the token, the 401 retry, logging and error mapping. Endpoint methods only build parameters and map results.
- **Typed edges.** Connex's loose JSON (mixed types, varying wrapper keys, trailing commas, local times) is normalised once in `ResponseParser` and `Payload`. Everything above them works with enums, value objects and `CarbonImmutable`.
- **Meaningful failures.** A `messageCode` means different things on different endpoints, so `ExceptionFactory` interprets it together with the `Endpoint`. Callers catch `InvalidOtp`, not `'01'`.
- **Server-owned state.** The browser never chooses which transaction or number an OTP confirms.
- **No tokens in URLs.** Clients receive a single-use code bound to their client id, redirect URI and PKCE verifier, and exchange it server-to-server (the authorization-code flow).
- **Extension by contract.** Swap behaviour by binding an interface (`ResolvesUsers`, `LoginResponder`, `IssuesTokens`, `BuildsTokenResponse`, `ConnexClient`). Swap data by extending the models. Swap the UI by publishing views and assets. Nothing needs to be forked.
- **Events over coupling.** The package records what happened and fires an event. What it means for your app (granting content, sending a welcome message, booking revenue) lives in your listeners.
