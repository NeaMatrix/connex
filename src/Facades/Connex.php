<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Facades;

use Illuminate\Support\Facades\Facade;
use NeaMatrix\Connex\Contracts\ConnexClient;
use NeaMatrix\Connex\Testing\FakeConnexClient;

/**
 * @method static \NeaMatrix\Connex\Data\ProtectedScript protectedScript(string $targetElement)
 * @method static \NeaMatrix\Connex\Data\OtpChallenge login(\NeaMatrix\Connex\Support\Msisdn|string $msisdn, string $transactionId, \NeaMatrix\Connex\Enums\DeviceType $device = \NeaMatrix\Connex\Enums\DeviceType::Web, ?string $otpSignature = null)
 * @method static \NeaMatrix\Connex\Data\LoginConfirmation confirmLogin(\NeaMatrix\Connex\Support\Msisdn|string $msisdn, string $otp, \NeaMatrix\Connex\Enums\DeviceType $device = \NeaMatrix\Connex\Enums\DeviceType::Web, ?string $campaignId = null, ?string $trackingId = null)
 * @method static \NeaMatrix\Connex\Data\SubscriberDetails subscriberDetails(\NeaMatrix\Connex\Support\Msisdn|string $msisdn)
 * @method static list<\NeaMatrix\Connex\Data\Charge> transactions(\NeaMatrix\Connex\Support\Msisdn|string $msisdn)
 * @method static list<\NeaMatrix\Connex\Data\SubscriptionPlan> plans(\NeaMatrix\Connex\Support\Msisdn|string $msisdn)
 * @method static \NeaMatrix\Connex\Data\OtpChallenge requestActivation(\NeaMatrix\Connex\Support\Msisdn|string $msisdn)
 * @method static \NeaMatrix\Connex\Data\SubscriptionChange confirmActivation(\NeaMatrix\Connex\Support\Msisdn|string $msisdn, string $otp, \NeaMatrix\Connex\Enums\DeviceType $device = \NeaMatrix\Connex\Enums\DeviceType::Web)
 * @method static \NeaMatrix\Connex\Data\OtpChallenge requestCancellation(\NeaMatrix\Connex\Support\Msisdn|string $msisdn)
 * @method static \NeaMatrix\Connex\Data\SubscriptionChange confirmCancellation(\NeaMatrix\Connex\Support\Msisdn|string $msisdn, string $otp, \NeaMatrix\Connex\Enums\DeviceType $device = \NeaMatrix\Connex\Enums\DeviceType::Web)
 * @method static \NeaMatrix\Connex\Data\SubscriptionChange cancelImmediately(\NeaMatrix\Connex\Support\Msisdn|string $msisdn)
 * @method static \NeaMatrix\Connex\Data\OtpChallenge requestPurchase(\NeaMatrix\Connex\Support\Msisdn|string $msisdn, int $productId, string $invoiceNo, string $transactionId)
 * @method static \NeaMatrix\Connex\Data\Charge confirmPurchase(\NeaMatrix\Connex\Support\Msisdn|string $msisdn, string $otp, string $invoiceNo, \NeaMatrix\Connex\Enums\DeviceType $device = \NeaMatrix\Connex\Enums\DeviceType::Web)
 * @method static \NeaMatrix\Connex\Data\OtpChallenge requestPlanChange(\NeaMatrix\Connex\Support\Msisdn|string $msisdn, string $planUuid)
 * @method static void confirmPlanChange(\NeaMatrix\Connex\Support\Msisdn|string $msisdn, string $otp, string $planUuid, \NeaMatrix\Connex\Enums\DeviceType $device = \NeaMatrix\Connex\Enums\DeviceType::Web)
 * @method static void sendSmsTemplate(\NeaMatrix\Connex\Support\Msisdn|string $msisdn, string $type, ?string $username = null, ?string $password = null)
 *
 * @see ConnexClient
 */
class Connex extends Facade
{
    /**
     * Replace the client with an in-memory fake for tests.
     */
    public static function fake(): FakeConnexClient
    {
        $fake = static::getFacadeApplication()?->make(FakeConnexClient::class) ?? new FakeConnexClient;
        static::swap($fake);
        static::getFacadeApplication()?->instance(ConnexClient::class, $fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return ConnexClient::class;
    }
}
