<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Client;

use NeaMatrix\Connex\Contracts\ConnexClient;
use NeaMatrix\Connex\Data\Charge;
use NeaMatrix\Connex\Data\LoginConfirmation;
use NeaMatrix\Connex\Data\OtpChallenge;
use NeaMatrix\Connex\Data\ProtectedScript;
use NeaMatrix\Connex\Data\SubscriberDetails;
use NeaMatrix\Connex\Data\SubscriptionChange;
use NeaMatrix\Connex\Data\SubscriptionPlan;
use NeaMatrix\Connex\Enums\DeviceType;
use NeaMatrix\Connex\Enums\Endpoint;
use NeaMatrix\Connex\Enums\SubscriptionStatus;
use NeaMatrix\Connex\Support\Msisdn;
use NeaMatrix\Connex\Support\MsisdnNormalizer;

/**
 * The real Connex API client.
 */
class HttpConnexClient implements ConnexClient
{
    public function __construct(
        private readonly Transport $transport,
        private readonly MsisdnNormalizer $msisdns,
        private readonly ClientOptions $options,
    ) {}

    public function protectedScript(string $targetElement): ProtectedScript
    {
        $response = $this->transport->send(Endpoint::ProtectedScript, [
            'targeted_element' => $targetElement,
        ]);

        return ProtectedScript::fromPayload($response->payload);
    }

    public function login(
        Msisdn|string $msisdn,
        string $transactionId,
        DeviceType $device = DeviceType::Web,
        ?string $otpSignature = null,
    ): OtpChallenge {
        return OtpChallenge::fromPayload($this->transport->send(Endpoint::Login, [
            'msisdn' => $this->number($msisdn),
            'transaction_identify' => $transactionId,
            'device_type' => $device->value,
            'otp_signature' => $otpSignature,
            'dcb_script_owner' => $this->options->scriptOwner,
        ])->payload);
    }

    public function confirmLogin(
        Msisdn|string $msisdn,
        string $otp,
        DeviceType $device = DeviceType::Web,
        ?string $campaignId = null,
        ?string $trackingId = null,
    ): LoginConfirmation {
        return LoginConfirmation::fromPayload($this->transport->send(Endpoint::LoginConfirm, [
            'msisdn' => $this->number($msisdn),
            'otp' => $otp,
            'device_type' => $device->value,
            'campaign_id' => $campaignId,
            'tracking_id' => $trackingId,
        ])->payload);
    }

    public function subscriberDetails(Msisdn|string $msisdn): SubscriberDetails
    {
        return SubscriberDetails::fromPayload($this->transport->send(Endpoint::SubscriberDetails, [
            'msisdn' => $this->number($msisdn),
        ])->payload);
    }

    public function transactions(Msisdn|string $msisdn): array
    {
        $payload = $this->transport->send(Endpoint::SubscriberTransactions, [
            'msisdn' => $this->number($msisdn),
        ])->payload;

        return array_map(Charge::fromPayload(...), $payload->list('list'));
    }

    public function plans(Msisdn|string $msisdn): array
    {
        $payload = $this->transport->send(Endpoint::SubscriptionList, [
            'msisdn' => $this->number($msisdn),
        ])->payload;

        return array_map(SubscriptionPlan::fromPayload(...), $payload->list('list'));
    }

    public function requestActivation(Msisdn|string $msisdn): OtpChallenge
    {
        return $this->challenge(Endpoint::SubscriptionActivation, ['msisdn' => $this->number($msisdn)]);
    }

    public function confirmActivation(Msisdn|string $msisdn, string $otp, DeviceType $device = DeviceType::Web): SubscriptionChange
    {
        return $this->change(Endpoint::SubscriptionActivationConfirm, $msisdn, $otp, $device, SubscriptionStatus::Active);
    }

    public function requestCancellation(Msisdn|string $msisdn): OtpChallenge
    {
        return $this->challenge(Endpoint::Unsubscribe, ['msisdn' => $this->number($msisdn)]);
    }

    public function confirmCancellation(Msisdn|string $msisdn, string $otp, DeviceType $device = DeviceType::Web): SubscriptionChange
    {
        return $this->change(Endpoint::UnsubscribeConfirm, $msisdn, $otp, $device, SubscriptionStatus::Cancelled);
    }

    public function cancelImmediately(Msisdn|string $msisdn): SubscriptionChange
    {
        $payload = $this->transport->send(Endpoint::DirectUnsubscribe, ['msisdn' => $this->number($msisdn)])->payload;

        return SubscriptionChange::fromPayload($payload, SubscriptionStatus::Cancelled);
    }

    public function requestPurchase(Msisdn|string $msisdn, int $productId, string $invoiceNo, string $transactionId): OtpChallenge
    {
        return $this->challenge(Endpoint::BuyProduct, [
            'msisdn' => $this->number($msisdn),
            'product_id' => $productId,
            'invoice_no' => $invoiceNo,
            'transaction_identify' => $transactionId,
        ]);
    }

    public function confirmPurchase(Msisdn|string $msisdn, string $otp, string $invoiceNo, DeviceType $device = DeviceType::Web): Charge
    {
        return Charge::fromPayload($this->transport->send(Endpoint::BuyProductConfirm, [
            'msisdn' => $this->number($msisdn),
            'otp' => $otp,
            'invoice_no' => $invoiceNo,
            'device_type' => $device->value,
        ])->payload);
    }

    public function requestPlanChange(Msisdn|string $msisdn, string $planUuid): OtpChallenge
    {
        return $this->challenge(Endpoint::Upgrade, ['msisdn' => $this->number($msisdn), 'uuid' => $planUuid]);
    }

    public function confirmPlanChange(Msisdn|string $msisdn, string $otp, string $planUuid, DeviceType $device = DeviceType::Web): void
    {
        $this->transport->send(Endpoint::UpgradeConfirm, [
            'msisdn' => $this->number($msisdn),
            'otp' => $otp,
            'device_type' => $device->value,
            'uuid' => $planUuid,
        ]);
    }

    public function sendSmsTemplate(Msisdn|string $msisdn, string $type, ?string $username = null, ?string $password = null): void
    {
        $this->transport->send(Endpoint::SendSmsTemplate, [
            'msisdn' => $this->number($msisdn),
            'type' => $type,
            'user_name' => $username,
            'password' => $password,
        ]);
    }

    /**
     * @param  array<string, scalar|null>  $parameters
     */
    private function challenge(Endpoint $endpoint, array $parameters): OtpChallenge
    {
        return OtpChallenge::fromPayload($this->transport->send($endpoint, $parameters)->payload);
    }

    private function change(
        Endpoint $endpoint,
        Msisdn|string $msisdn,
        string $otp,
        DeviceType $device,
        SubscriptionStatus $expected,
    ): SubscriptionChange {
        $payload = $this->transport->send($endpoint, [
            'msisdn' => $this->number($msisdn),
            'otp' => $otp,
            'device_type' => $device->value,
        ])->payload;

        return SubscriptionChange::fromPayload($payload, $expected);
    }

    private function number(Msisdn|string $msisdn): string
    {
        return $this->msisdns->normalize($msisdn)->international;
    }
}
