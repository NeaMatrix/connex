<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Testing;

use Carbon\CarbonImmutable;
use NeaMatrix\Connex\Contracts\ConnexClient;
use NeaMatrix\Connex\Data\Charge;
use NeaMatrix\Connex\Data\LoginConfirmation;
use NeaMatrix\Connex\Data\OtpChallenge;
use NeaMatrix\Connex\Data\ProtectedScript;
use NeaMatrix\Connex\Data\SubscriberDetails;
use NeaMatrix\Connex\Data\SubscriptionChange;
use NeaMatrix\Connex\Data\SubscriptionPlan;
use NeaMatrix\Connex\Enums\DeviceType;
use NeaMatrix\Connex\Enums\SubscriberState;
use NeaMatrix\Connex\Enums\SubscriptionStatus;
use NeaMatrix\Connex\Exceptions\ConnexException;
use NeaMatrix\Connex\Exceptions\FraudDetected;
use NeaMatrix\Connex\Exceptions\InvalidOtp;
use NeaMatrix\Connex\Exceptions\SubscriptionAlreadyActive;
use NeaMatrix\Connex\Exceptions\SubscriptionAlreadyCancelled;
use NeaMatrix\Connex\Support\Msisdn;
use NeaMatrix\Connex\Support\MsisdnNormalizer;

/**
 * An in-memory Connex that behaves like the real one: OTPs must match,
 * subscriptions change state, fraud and failures can be simulated.
 *
 *     $connex = Connex::fake()->withOtp('4321')->withSubscriber('0920920110');
 *     // ... exercise the app ...
 *     $connex->assertCalled('confirmLogin');
 */
class FakeConnexClient implements ConnexClient
{
    use RecordsCalls;

    private string $otp = '1234';

    /** @var array<string, array{status: SubscriptionStatus, expires_at: CarbonImmutable}> */
    private array $subscribers = [];

    /** @var array<string, true> */
    private array $fraudulent = [];

    /** @var array<string, ConnexException> */
    private array $failures = [];

    private int $sequence = 0;

    public function __construct(
        private readonly MsisdnNormalizer $msisdns = new MsisdnNormalizer,
    ) {}

    public function withOtp(string $otp): static
    {
        $this->otp = $otp;

        return $this;
    }

    public function withSubscriber(
        Msisdn|string $msisdn,
        SubscriptionStatus $status = SubscriptionStatus::Active,
        ?CarbonImmutable $expiresAt = null,
    ): static {
        $this->subscribers[$this->number($msisdn)] = [
            'status' => $status,
            'expires_at' => $expiresAt ?? CarbonImmutable::now()->addDay(),
        ];

        return $this;
    }

    /** Make Connex's anti-fraud check reject this number. */
    public function fraudulent(Msisdn|string $msisdn): static
    {
        $this->fraudulent[$this->number($msisdn)] = true;

        return $this;
    }

    /** Make every call to $method throw $exception. */
    public function failWith(string $method, ConnexException $exception): static
    {
        $this->failures[$method] = $exception;

        return $this;
    }

    public function protectedScript(string $targetElement): ProtectedScript
    {
        $this->record(__FUNCTION__, compact('targetElement'));

        return new ProtectedScript('fake-transaction-'.++$this->sequence, "document.dispatchEvent(new Event('gateway-load'));");
    }

    public function login(Msisdn|string $msisdn, string $transactionId, DeviceType $device = DeviceType::Web, ?string $otpSignature = null): OtpChallenge
    {
        $number = $this->number($msisdn);
        $this->record(__FUNCTION__, compact('number', 'transactionId', 'device'));

        if (isset($this->fraudulent[$number])) {
            throw new FraudDetected('This operation is not allowed');
        }

        $known = $this->subscribers[$number] ?? null;

        return new OtpChallenge(
            msisdn: $number,
            message: 'OTP send success',
            subscriber: $known ? SubscriberState::Existing : SubscriberState::New,
            status: $known['status'] ?? null,
            expiresAt: $known['expires_at'] ?? null,
            operator: 'Libyana',
        );
    }

    public function confirmLogin(Msisdn|string $msisdn, string $otp, DeviceType $device = DeviceType::Web, ?string $campaignId = null, ?string $trackingId = null): LoginConfirmation
    {
        $number = $this->number($msisdn);
        $this->record(__FUNCTION__, compact('number', 'device', 'campaignId', 'trackingId'));
        $this->verifyOtp($otp);

        $isNew = ! isset($this->subscribers[$number]);
        $this->subscribers[$number] ??= ['status' => SubscriptionStatus::Active, 'expires_at' => CarbonImmutable::now()->addDay()];

        return new LoginConfirmation(
            msisdn: $number,
            subscriber: $isNew ? SubscriberState::Created : SubscriberState::Existing,
            status: $this->subscribers[$number]['status'],
            expiresAt: $this->subscribers[$number]['expires_at'],
            operator: 'Libyana',
        );
    }

    public function subscriberDetails(Msisdn|string $msisdn): SubscriberDetails
    {
        $number = $this->number($msisdn);
        $this->record(__FUNCTION__, compact('number'));
        $known = $this->subscribers[$number] ?? null;

        return new SubscriberDetails($number, $known !== null, $known['status'] ?? null, $known['expires_at'] ?? null);
    }

    public function transactions(Msisdn|string $msisdn): array
    {
        $this->record(__FUNCTION__, ['number' => $this->number($msisdn)]);

        return [];
    }

    public function plans(Msisdn|string $msisdn): array
    {
        $this->record(__FUNCTION__, ['number' => $this->number($msisdn)]);

        return [new SubscriptionPlan('fake-plan', 'Daily', 24, 24, 1, 0.25)];
    }

    public function requestActivation(Msisdn|string $msisdn): OtpChallenge
    {
        $number = $this->number($msisdn);
        $this->record(__FUNCTION__, compact('number'));

        if (($this->subscribers[$number]['status'] ?? null) === SubscriptionStatus::Active) {
            throw new SubscriptionAlreadyActive('subscription is already active');
        }

        return new OtpChallenge($number, 'OTP send success');
    }

    public function confirmActivation(Msisdn|string $msisdn, string $otp, DeviceType $device = DeviceType::Web): SubscriptionChange
    {
        $this->record(__FUNCTION__, ['number' => $this->number($msisdn), 'device' => $device]);
        $this->verifyOtp($otp);

        return $this->changeStatus($msisdn, SubscriptionStatus::Active, CarbonImmutable::now()->addDay());
    }

    public function requestCancellation(Msisdn|string $msisdn): OtpChallenge
    {
        $number = $this->number($msisdn);
        $this->record(__FUNCTION__, compact('number'));

        if (($this->subscribers[$number]['status'] ?? null) === SubscriptionStatus::Cancelled) {
            throw new SubscriptionAlreadyCancelled('subscription is already cancel');
        }

        return new OtpChallenge($number, 'OTP send success');
    }

    public function confirmCancellation(Msisdn|string $msisdn, string $otp, DeviceType $device = DeviceType::Web): SubscriptionChange
    {
        $this->record(__FUNCTION__, ['number' => $this->number($msisdn), 'device' => $device]);
        $this->verifyOtp($otp);

        return $this->changeStatus($msisdn, SubscriptionStatus::Cancelled);
    }

    public function cancelImmediately(Msisdn|string $msisdn): SubscriptionChange
    {
        $this->record(__FUNCTION__, ['number' => $this->number($msisdn)]);

        return $this->changeStatus($msisdn, SubscriptionStatus::Cancelled);
    }

    public function requestPurchase(Msisdn|string $msisdn, int $productId, string $invoiceNo, string $transactionId): OtpChallenge
    {
        $number = $this->number($msisdn);
        $this->record(__FUNCTION__, compact('number', 'productId', 'invoiceNo', 'transactionId'));

        return new OtpChallenge($number, 'OTP send success');
    }

    public function confirmPurchase(Msisdn|string $msisdn, string $otp, string $invoiceNo, DeviceType $device = DeviceType::Web): Charge
    {
        $this->record(__FUNCTION__, ['number' => $this->number($msisdn), 'invoiceNo' => $invoiceNo]);
        $this->verifyOtp($otp);

        return new Charge('fake-charge-'.++$this->sequence, 0.25, CarbonImmutable::now(), $invoiceNo, 'success');
    }

    public function requestPlanChange(Msisdn|string $msisdn, string $planUuid): OtpChallenge
    {
        $number = $this->number($msisdn);
        $this->record(__FUNCTION__, compact('number', 'planUuid'));

        return new OtpChallenge($number, 'OTP send success');
    }

    public function confirmPlanChange(Msisdn|string $msisdn, string $otp, string $planUuid, DeviceType $device = DeviceType::Web): void
    {
        $this->record(__FUNCTION__, ['number' => $this->number($msisdn), 'planUuid' => $planUuid]);
        $this->verifyOtp($otp);
    }

    public function sendSmsTemplate(Msisdn|string $msisdn, string $type, ?string $username = null, ?string $password = null): void
    {
        $this->record(__FUNCTION__, ['number' => $this->number($msisdn), 'type' => $type]);
    }

    protected function beforeCall(string $method): void
    {
        if (isset($this->failures[$method])) {
            throw $this->failures[$method];
        }
    }

    private function verifyOtp(string $otp): void
    {
        if (! hash_equals($this->otp, $otp)) {
            throw new InvalidOtp('OTP incorrect');
        }
    }

    private function changeStatus(Msisdn|string $msisdn, SubscriptionStatus $status, ?CarbonImmutable $expiresAt = null): SubscriptionChange
    {
        $number = $this->number($msisdn);
        $expiresAt ??= $this->subscribers[$number]['expires_at'] ?? CarbonImmutable::now();
        $this->subscribers[$number] = ['status' => $status, 'expires_at' => $expiresAt];

        return new SubscriptionChange($number, $status, $expiresAt);
    }

    private function number(Msisdn|string $msisdn): string
    {
        return $this->msisdns->normalize($msisdn)->international;
    }
}
