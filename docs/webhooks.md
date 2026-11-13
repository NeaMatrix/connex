# Webhooks

Connex pushes four notifications. Give Ebtekar these URLs:

```
https://your-app.com/connex/webhooks/subscribe?token=YOUR_SECRET
https://your-app.com/connex/webhooks/unsubscribe?token=YOUR_SECRET
https://your-app.com/connex/webhooks/reactivate?token=YOUR_SECRET
https://your-app.com/connex/webhooks/renewal?token=YOUR_SECRET
```

## Security

Connex doesn't sign its notifications, so:

- `CONNEX_WEBHOOK_SECRET` is **required**. It is compared in constant time against `?token=` or the `X-Connex-Token` header. Until it is set, every webhook answers 503.
- `CONNEX_WEBHOOK_IPS` (for example `1.2.3.4,10.0.0.0/8`) also restricts calls to Connex's addresses. Ask Ebtekar for them.
- Payloads are validated, and the msisdn must be a valid mobile number.

Responses use the format Connex expects: `{"messageCode":"00","message":"successfull renewal"}`. Errors return `messageCode` `01` with HTTP 401, 403, 422 or 503.

## Events

| Event | Fired when | Notification fields |
| --- | --- | --- |
| `SubscriberSubscribed` | joined by SMS | `msisdn`, `subscribedAt`, `expiresAt`, `channel`, `serviceName`, `planName` |
| `SubscriberUnsubscribed` | cancelled outside your site | `msisdn`, `cancelledAt`, `cancelledBy`, `serviceName`, `trackingId` |
| `SubscriberReactivated` | re-activated outside your site | `msisdn`, `reactivatedAt`, `reactivatedBy`, `serviceName`, `trackingId` |
| `SubscriptionRenewed` | charged for another period (once per transaction) | `msisdn`, `transactionId`, `amount`, `chargedAt`, `expiresAt`, `conversationId`, `serviceName` |

All four live in `NeaMatrix\Connex\Webhooks\Events`. Each has a `$notification` and a `$subscription` (`null` when `subscriptions.store` is off). The local mirror is updated and the event fires inside the same database transaction, before Connex receives its answer. Queue heavy listeners with `ShouldQueue`.

```php
Event::listen(function (SubscriptionRenewed $event) {
    Revenue::record($event->notification->amount, $event->notification->transactionId);
});
```
