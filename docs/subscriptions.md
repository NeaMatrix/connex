# Subscriptions and access

## The local mirror

Connex is the source of truth. The package keeps a local copy in `connex_subscriptions`, one row per phone number linked to its owner through a morph relation, and keeps every debit in `connex_charges`. The copy is updated by:

- a confirmed login (including the initial bill, if any)
- the account page, which refreshes from `subscriber-details` on every visit
- activation and cancellation
- every webhook

Writes never erase a known value with `null`. Charges are unique by Connex transaction id, so a repeated renewal notification is recorded only once.

## Access rules

```php
$user->hasConnexAccess();          // bool
$user->connexAccess();             // SubscriptionAccess enum
$user->connexSubscription;         // ConnexSubscription|null

ConnexSubscription::withAccess()->count();   // subscribers who may use paid content now
```

`SubscriptionAccess` applies the Connex rules:

| Case | Value | `allowsAccess()` |
| --- | --- | --- |
| active, not expired | `Granted` | yes |
| active, expired (renewal failed) | `InsufficientBalance` | no |
| cancelled, not expired | `GracePeriod` | yes |
| cancelled, expired | `Expired` | no |
| never subscribed / pending | `None` | no |

## Protecting routes

```php
Route::middleware(['auth', 'connex.access'])->group(function () {
    Route::get('/lessons/{lesson}', ShowLesson::class);
});
```

Blocked JSON requests get **402** with `{"access": "insufficient_balance", "message": "…"}`. Browsers are redirected to the account page, which explains why access is blocked.

## Activating and cancelling

Apps and frontends use the JSON API under `/connex/api/subscription` (see [Apps and frontends](clients.md#the-account-page-connex-requirement)). From PHP:

### From your own code

```php
use NeaMatrix\Connex\Subscriptions\SubscriptionManager;

$manager = app(SubscriptionManager::class);

$manager->requestCancellation($user);                     // Connex texts an OTP
$subscription = $manager->confirmCancellation($user, $otp);

$manager->requestActivation($user);
$manager->confirmActivation($user, $otp, DeviceType::Android);

$manager->refresh($user);                                 // pull the latest state
```

These methods throw `NotSubscribed` when the user has no linked subscription, and a `ConnexException` subclass when Connex refuses ([API client](api-client.md#exceptions)).

## Keeping it fresh

Webhooks keep the mirror current. As a safety net, you can refresh subscriptions that are about to expire from a scheduled job:

```php
Schedule::call(function (SubscriptionManager $manager) {
    ConnexSubscription::with('owner')
        ->where('expires_at', '<', now()->addHour())
        ->each(fn ($s) => $s->owner && $manager->refresh($s->owner));
})->hourly();
```
