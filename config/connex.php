<?php

declare(strict_types=1);

use NeaMatrix\Connex\Models\ConnexCharge;
use NeaMatrix\Connex\Models\ConnexSubscription;

return [

    /*
    |--------------------------------------------------------------------------
    | Connex API
    |--------------------------------------------------------------------------
    | The sandbox is connextst.ebtekarcloud.com; Ebtekar gives you the
    | production host and the service account (email + password).
    */

    'base_url' => env('CONNEX_BASE_URL', 'https://connextst.ebtekarcloud.com/external-api'),

    'credentials' => [
        'email' => env('CONNEX_EMAIL'),
        'password' => env('CONNEX_PASSWORD'),
    ],

    'http' => [
        'timeout' => (int) env('CONNEX_TIMEOUT', 15),
        'connect_timeout' => (int) env('CONNEX_CONNECT_TIMEOUT', 5),
    ],

    /* Cache store for the API token. null uses the default store. */
    'cache_store' => env('CONNEX_CACHE_STORE'),

    /* Your Evina account name, only if you already use Evina DCBProtect directly. */
    'script_owner' => env('CONNEX_DCB_SCRIPT_OWNER'),

    /* Connex sends local Libyan times without an offset. */
    'timezone' => env('CONNEX_TIMEZONE', 'Africa/Tripoli'),

    'otp_length' => 4,

    'msisdn' => [
        'country_code' => '218',
        'national_length' => 9,
        'mobile_prefixes' => ['91', '92', '93', '94', '95'],
    ],

    /* Log every Connex call (never OTPs or passwords) to this channel. */
    'logging' => [
        'channel' => env('CONNEX_LOG_CHANNEL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Local subscription mirror
    |--------------------------------------------------------------------------
    */

    'subscriptions' => [
        'store' => true,
    ],

    'models' => [
        'subscription' => ConnexSubscription::class,
        'charge' => ConnexCharge::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    | How the default resolver finds or creates the user for a phone number.
    | "format" is how numbers are stored: international (2189...) or national (09...).
    */

    'user' => [
        'model' => env('CONNEX_USER_MODEL', 'App\\Models\\User'),
        'msisdn_column' => 'phone',
        'format' => 'international',
        'password_column' => 'password',
    ],

    /*
    |--------------------------------------------------------------------------
    | Login pages
    |--------------------------------------------------------------------------
    */

    'login' => [
        'enabled' => true,
        'prefix' => 'connex',
        'middleware' => ['web'],
        'view' => 'connex::login',

        /* Guard used to sign web visitors in. null uses the default guard. */
        'guard' => null,
        'redirect_to' => '/',

        /* App deep link that receives ?code=... after a webview login, e.g. tawajah://connex. */
        'mobile_callback_url' => env('CONNEX_MOBILE_CALLBACK_URL'),
        'handoff_ttl' => 120,
        'api_middleware' => ['api'],

        /* id of the phone step's submit button, watched by the anti-fraud script. */
        'target_element' => 'connex-submit',
        'gateway_timeout_ms' => 15000,
        'resend_cooldown' => 60,

        /* Requests per minute, per IP. */
        'throttle' => [
            'script' => 20,
            'otp' => 5,
            'confirm' => 10,
        ],
    ],

    'account' => [
        'enabled' => true,
        'middleware' => ['web', 'auth'],
        'view' => 'connex::account',
        'logout_url' => null,
    ],

    'pages' => [
        'brand_name' => env('APP_NAME', 'Connex'),
        'brand_color' => '#c8333b',
        'locales' => ['ar', 'en'],
        'default_locale' => 'ar',
        'home_url' => '/',
        'about_url' => null,
        'terms_url' => null,
        'privacy_url' => null,
        'contact_url' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks (Connex notifications)
    |--------------------------------------------------------------------------
    | Give Connex: https://your-app/{prefix}/{subscribe|unsubscribe|reactivate|renewal}?token={secret}
    */

    'webhooks' => [
        'enabled' => true,
        'prefix' => 'connex/webhooks',
        'middleware' => ['api'],
        'secret' => env('CONNEX_WEBHOOK_SECRET'),
        'allowed_ips' => array_values(array_filter(explode(',', (string) env('CONNEX_WEBHOOK_IPS', '')))),
    ],

];
