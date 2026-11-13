<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Contracts;

use NeaMatrix\Connex\Data\Charge;
use NeaMatrix\Connex\Data\LoginConfirmation;
use NeaMatrix\Connex\Data\OtpChallenge;
use NeaMatrix\Connex\Data\ProtectedScript;
use NeaMatrix\Connex\Data\SubscriberDetails;
use NeaMatrix\Connex\Data\SubscriptionChange;
use NeaMatrix\Connex\Data\SubscriptionPlan;
use NeaMatrix\Connex\Enums\DeviceType;
use NeaMatrix\Connex\Exceptions\ConnexException;
use NeaMatrix\Connex\Support\Msisdn;

/**
 * Every operation of the Connex external API.
 *
 * Phone numbers may be given in any common format; they are normalised before
 * being sent. Every method throws a {@see ConnexException} subclass on failure.
 */
interface ConnexClient
{
    /** Fetch the anti-fraud script for the page whose submit button matches $targetElement. */
    public function protectedScript(string $targetElement): ProtectedScript;

    /** Start a login: Connex texts the subscriber an OTP (or asks to choose a plan). */
    public function login(
        Msisdn|string $msisdn,
        string $transactionId,
        DeviceType $device = DeviceType::Web,
        ?string $otpSignature = null,
    ): OtpChallenge;

    public function confirmLogin(
        Msisdn|string $msisdn,
        string $otp,
        DeviceType $device = DeviceType::Web,
        ?string $campaignId = null,
        ?string $trackingId = null,
    ): LoginConfirmation;

    public function subscriberDetails(Msisdn|string $msisdn): SubscriberDetails;

    /** @return list<Charge> */
    public function transactions(Msisdn|string $msisdn): array;

    /** @return list<SubscriptionPlan> */
    public function plans(Msisdn|string $msisdn): array;

    public function requestActivation(Msisdn|string $msisdn): OtpChallenge;

    public function confirmActivation(Msisdn|string $msisdn, string $otp, DeviceType $device = DeviceType::Web): SubscriptionChange;

    public function requestCancellation(Msisdn|string $msisdn): OtpChallenge;

    public function confirmCancellation(Msisdn|string $msisdn, string $otp, DeviceType $device = DeviceType::Web): SubscriptionChange;

    /** Cancel without an OTP. Use only for actions the subscriber already confirmed elsewhere. */
    public function cancelImmediately(Msisdn|string $msisdn): SubscriptionChange;

    /** Start a one-off purchase. $invoiceNo is your own reference for the order. */
    public function requestPurchase(Msisdn|string $msisdn, int $productId, string $invoiceNo, string $transactionId): OtpChallenge;

    public function confirmPurchase(Msisdn|string $msisdn, string $otp, string $invoiceNo, DeviceType $device = DeviceType::Web): Charge;

    /** Start moving the subscriber to another plan. */
    public function requestPlanChange(Msisdn|string $msisdn, string $planUuid): OtpChallenge;

    public function confirmPlanChange(Msisdn|string $msisdn, string $otp, string $planUuid, DeviceType $device = DeviceType::Web): void;

    /** Send one of the SMS templates configured on Connex (e.g. "account"). */
    public function sendSmsTemplate(Msisdn|string $msisdn, string $type, ?string $username = null, ?string $password = null): void;
}
