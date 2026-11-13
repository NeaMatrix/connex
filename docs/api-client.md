# API client

Use the `Connex` facade, or inject `NeaMatrix\Connex\Contracts\ConnexClient`. Phone numbers can be in any common format. Every method returns a typed, read-only object.

| Method | Connex endpoint | Returns |
| --- | --- | --- |
| `protectedScript($targetElement)` | `protected-script` | `ProtectedScript` (`transactionId`, `script`) |
| `login($msisdn, $transactionId, $device, $otpSignature)` | `login` | `OtpChallenge` |
| `confirmLogin($msisdn, $otp, $device, $campaignId, $trackingId)` | `login-confirm` | `LoginConfirmation` |
| `subscriberDetails($msisdn)` | `subscriber-details` | `SubscriberDetails` |
| `transactions($msisdn)` | `subscriber-transactions` | `list<Charge>` |
| `plans($msisdn)` | `subscription-list` | `list<SubscriptionPlan>` |
| `requestActivation($msisdn)` | `subscription-activation` | `OtpChallenge` |
| `confirmActivation($msisdn, $otp, $device)` | `subscription-activation-confirm` | `SubscriptionChange` |
| `requestCancellation($msisdn)` | `unsubscribe` | `OtpChallenge` |
| `confirmCancellation($msisdn, $otp, $device)` | `unsubscribe-confirm` | `SubscriptionChange` |
| `cancelImmediately($msisdn)` | `direct-unsubscribe` | `SubscriptionChange` |
| `requestPurchase($msisdn, $productId, $invoiceNo, $transactionId)` | `buy-product` | `OtpChallenge` |
| `confirmPurchase($msisdn, $otp, $invoiceNo, $device)` | `buy-product-confirm` | `Charge` |
| `requestPlanChange($msisdn, $planUuid)` | `upgrade` | `OtpChallenge` |
| `confirmPlanChange($msisdn, $otp, $planUuid, $device)` | `upgrade-confirm` | — |
| `sendSmsTemplate($msisdn, $type, $username, $password)` | `send-sms-template` | — |

`$device` is a `DeviceType` (`Web`, `Android`, `Ios`) and defaults to `Web`.

## Results

- **`OtpChallenge`**: `msisdn`, `message`, `subscriber` (`SubscriberState`), `status`, `expiresAt`, `operator`, `plans`. Also `requiresPlanSelection()` and `awaitsSmsConfirmation()` (two-factor login, where the subscriber replies to an SMS).
- **`LoginConfirmation`**: `msisdn`, `subscriber`, `status`, `expiresAt`, `operator`, `charge` (the initial bill, if any). Also `isNewSubscriber()`.
- **`SubscriberDetails`**: `exists`, `status`, `expiresAt`, `hoursRemaining`, `planName`, `deviceType`, the last login, cancellation, renewal and charge dates, and `access()`.
- **`Charge`**: `transactionId`, `amount`, `chargedAt`, `invoiceNo`, `status`, `productId`.

Dates are `CarbonImmutable`, converted from Connex's Libyan local time to your app timezone.

## Exceptions

Every failure extends `NeaMatrix\Connex\Exceptions\ConnexException`. The exception's `->response` holds the raw Connex response, when there was one.

| Exception | When |
| --- | --- |
| `ConnectionFailed` | Connex didn't answer (timeout, DNS, TLS) |
| `AuthenticationFailed` | Your service credentials are wrong or missing |
| `UnexpectedResponse` | The body could not be read |
| `RequestRejected` | Connex refused, base class for the cases below |
| ↳ `InvalidOtp` | Wrong or expired code (`01` on a confirm endpoint) |
| ↳ `FraudDetected` | `300` |
| ↳ `TooManyRequests` | `05` / HTTP 429 |
| ↳ `InsufficientBalance` | `21` |
| ↳ `SubscriptionAlreadyActive` | activation while active |
| ↳ `SubscriptionAlreadyCancelled` | `12` |
| ↳ `InvalidProduct` | `18` |

Invalid phone numbers throw `InvalidMsisdn` (an `InvalidArgumentException`) before any request is sent. In forms, use the `NeaMatrix\Connex\Rules\ValidMsisdn` rule.

## Robustness

- The token is cached until 60 seconds before it expires. If Connex answers 401, the package fetches a new token and retries once.
- Connex's habit of trailing commas in JSON is tolerated.
- Requests use explicit timeouts, and connection errors become `ConnectionFailed`.
