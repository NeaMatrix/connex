<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use NeaMatrix\Connex\Webhooks\Events\SubscriberSubscribed;

$payload = ['msisdn' => '218920920110', 'created_at' => '2023-09-18 00:05:25', 'expiration_date' => '2023-09-19 00:05:25'];

it('rejects calls without the shared secret', function (?string $token, int $status) use ($payload): void {
    Event::fake();

    $this->postJson('/connex/webhooks/subscribe'.($token ? "?token={$token}" : ''), $payload)->assertStatus($status);

    Event::assertNotDispatched(SubscriberSubscribed::class);
})->with([
    'missing' => [null, 401],
    'wrong' => ['nope', 401],
]);

it('accepts the secret as a header', function () use ($payload): void {
    $this->postJson('/connex/webhooks/subscribe', $payload, ['X-Connex-Token' => 'webhook-secret'])->assertOk();
});

it('refuses every call until a secret is configured', function () use ($payload): void {
    config(['connex.webhooks.secret' => null]);

    $this->postJson('/connex/webhooks/subscribe?token=', $payload)->assertStatus(503);
});

it('limits calls to the allowed IP ranges', function () use ($payload): void {
    config(['connex.webhooks.allowed_ips' => ['10.0.0.0/8']]);

    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->postJson('/connex/webhooks/subscribe?token=webhook-secret', $payload)->assertForbidden();
    $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
        ->postJson('/connex/webhooks/subscribe?token=webhook-secret', $payload)->assertOk();
});

it('rejects invalid payloads in the Connex format', function (): void {
    Event::fake();

    $this->postJson('/connex/webhooks/renewal?token=webhook-secret', ['msisdn' => 'not-a-number'])
        ->assertStatus(422)
        ->assertJson(['messageCode' => '01']);

    Event::assertNotDispatched(SubscriberSubscribed::class);
});
