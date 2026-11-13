# Testing

## Faking Connex in your app's tests

`Connex::fake()` replaces the client with an in-memory Connex that behaves like the real one:

- OTPs must match.
- New numbers become active subscribers on login.
- Activation and cancellation change state, and refuse when they don't apply.

```php
use NeaMatrix\Connex\Facades\Connex;

it('signs a learner in', function () {
    $connex = Connex::fake()->withOtp('4321');

    $this->get('/connex/login');
    $this->getJson('/connex/login/script');
    $this->postJson('/connex/login/otp', ['msisdn' => '0920920110', 'accepted_terms' => true])->assertOk();
    $this->postJson('/connex/login/otp/confirm', ['otp' => '4321'])->assertOk();

    $this->assertAuthenticated();
    $connex->assertOtpRequested('0920920110');
});
```

| Setup | |
| --- | --- |
| `withOtp('4321')` | The code every confirm endpoint accepts (default `1234`) |
| `withSubscriber($msisdn, $status, $expiresAt)` | An existing subscriber |
| `fraudulent($msisdn)` | `login` throws `FraudDetected` |
| `failWith('subscriberDetails', new ConnectionFailed('down'))` | Any method throws |

| Assertion | |
| --- | --- |
| `assertCalled($method, ?fn (array $args) => bool, times: 1)` | |
| `assertNotCalled($method)` / `assertNothingCalled()` | |
| `assertOtpRequested($msisdn)` | |
| `calls($method)` | Recorded arguments |

Factories for the mirror:

```php
ConnexSubscription::factory()->active()->ownedBy($user)->create();
// also: ->unpaid(), ->cancelled(), ->expired()
```

## Testing against the real sandbox

```bash
php artisan connex:check --msisdn=09XXXXXXXX
```

This checks the credentials and the anti-fraud endpoint, and optionally reads a subscriber. To do a full login, open `/connex/login` with a test SIM from Ebtekar.

## The package's own suite

```bash
composer check   # Pint, PHPStan level 8, Pest
composer demo    # sample frontend: PKCE login → Sanctum token → protected API, against the fake (code 1234)
```
