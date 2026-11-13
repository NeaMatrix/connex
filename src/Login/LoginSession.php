<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Login;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Session\Session;
use NeaMatrix\Connex\Enums\DeviceType;
use NeaMatrix\Connex\Login\Clients\AuthorizationRequest;

/**
 * Server-side state of one login attempt, kept in the session so the browser
 * never chooses which number or transaction an OTP is confirmed for.
 */
class LoginSession
{
    private const string KEY = 'connex.login';

    public function __construct(
        private readonly Session $session,
    ) {}

    /**
     * Called when the login page opens. Remembers where the subscriber came from.
     */
    public function begin(
        DeviceType $device,
        ?AuthorizationRequest $authorization = null,
        ?string $campaignId = null,
        ?string $trackingId = null,
    ): void {
        $this->session->put(self::KEY, [
            'device' => $device->value,
            'authorization' => $authorization?->toArray(),
            'campaign_id' => $campaignId,
            'tracking_id' => $trackingId,
        ]);
    }

    /** The client that sent the subscriber here, or null for a session login. */
    public function authorization(): ?AuthorizationRequest
    {
        $data = $this->session->get(self::KEY.'.authorization');

        /** @var array{client: string, redirect_uri: string, state?: string|null, code_challenge?: string|null, response_mode?: string}|null $data */
        return is_array($data) ? AuthorizationRequest::fromArray($data) : null;
    }

    public function hasStarted(): bool
    {
        return $this->session->has(self::KEY.'.device');
    }

    public function rememberTransaction(string $transactionId): void
    {
        $this->session->put(self::KEY.'.transaction_id', $transactionId);
    }

    public function rememberOtpRequest(string $msisdn): void
    {
        $this->session->put(self::KEY.'.msisdn', $msisdn);
        $this->session->put(self::KEY.'.otp_requested_at', CarbonImmutable::now()->getTimestamp());
    }

    public function device(): DeviceType
    {
        return DeviceType::tryFrom((string) $this->session->get(self::KEY.'.device')) ?? DeviceType::Web;
    }

    public function transactionId(): ?string
    {
        return $this->stringValue('transaction_id');
    }

    public function msisdn(): ?string
    {
        return $this->stringValue('msisdn');
    }

    public function campaignId(): ?string
    {
        return $this->stringValue('campaign_id');
    }

    public function trackingId(): ?string
    {
        return $this->stringValue('tracking_id');
    }

    /** Seconds until another OTP may be requested. */
    public function resendAvailableIn(int $cooldownSeconds): int
    {
        $requestedAt = $this->session->get(self::KEY.'.otp_requested_at');

        if (! is_int($requestedAt)) {
            return 0;
        }

        return max($requestedAt + $cooldownSeconds - CarbonImmutable::now()->getTimestamp(), 0);
    }

    public function finish(): void
    {
        $device = $this->device();
        $this->session->forget(self::KEY);
        $this->session->put(self::KEY.'.device', $device->value);
    }

    private function stringValue(string $key): ?string
    {
        $value = $this->session->get(self::KEY.'.'.$key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
