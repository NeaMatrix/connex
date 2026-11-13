<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Enums;

/**
 * Every Connex external-api endpoint, with its HTTP method and path.
 */
enum Endpoint: string
{
    case AuthLogin = 'auth-login';
    case ProtectedScript = 'protected-script';
    case Login = 'login';
    case LoginConfirm = 'login-confirm';
    case SubscriberDetails = 'subscriber-details';
    case SubscriberTransactions = 'subscriber-transactions';
    case SubscriptionList = 'subscription-list';
    case SubscriptionActivation = 'subscription-activation';
    case SubscriptionActivationConfirm = 'subscription-activation-confirm';
    case Unsubscribe = 'unsubscribe';
    case UnsubscribeConfirm = 'unsubscribe-confirm';
    case DirectUnsubscribe = 'direct-unsubscribe';
    case BuyProduct = 'buy-product';
    case BuyProductConfirm = 'buy-product-confirm';
    case Upgrade = 'upgrade';
    case UpgradeConfirm = 'upgrade-confirm';
    case SendSmsTemplate = 'send-sms-template';

    public function method(): string
    {
        return match ($this) {
            self::ProtectedScript,
            self::SubscriberDetails,
            self::SubscriberTransactions,
            self::SubscriptionList => 'GET',
            default => 'POST',
        };
    }

    public function path(): string
    {
        return '/'.$this->value;
    }

    /** Endpoints where messageCode "01" means the subscriber typed a wrong OTP. */
    public function confirmsOtp(): bool
    {
        return in_array($this, [
            self::LoginConfirm,
            self::SubscriptionActivationConfirm,
            self::UnsubscribeConfirm,
            self::BuyProductConfirm,
            self::UpgradeConfirm,
        ], true);
    }
}
