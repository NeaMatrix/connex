<?php

declare(strict_types=1);

use NeaMatrix\Connex\Models\ConnexCharge;
use NeaMatrix\Connex\Models\ConnexSubscription;

return [

    /*
    |--------------------------------------------------------------------------
    | Connex API
    |--------------------------------------------------------------------------
    | Sandbox: https://connextst.ebtekarcloud.com/external-api
    | Ebtekar gives you the production host and the service account.
    */

    'base_url' => env('CONNEX_BASE_URL'),

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
    | Clients
    |--------------------------------------------------------------------------
    | The apps that send subscribers to the login page and get a Sanctum token
    | back. Each may only be redirected to its listed URIs (exact match).
    |
    | /connex/login?client=mobile&redirect_uri=tawajah://auth/connex&state=...
    |              &code_challenge=...&code_challenge_method=S256&device=android
    */

    'clients' => [
        // 'mobile' => [
        //     'redirect_uris' => ['tawajah://auth/connex'],
        //     'pkce' => true,
        // ],
        // 'web' => [
        //     'redirect_uris' => ['https://app.example.com/auth/connex/callback'],
        //     'pkce' => true,
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tokens
    |--------------------------------------------------------------------------
    | Issued by POST /connex/api/token in exchange for a login code.
    */

    'tokens' => [
        'name' => 'connex',
        'abilities' => ['*'],
        /* Minutes; null keeps Sanctum's own expiration setting. */
        'expires_in' => null,
        /* Seconds a login code stays valid. */
        'code_ttl' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Login page
    |--------------------------------------------------------------------------
    */

    'login' => [
        'enabled' => true,
        'prefix' => 'connex',
        'middleware' => ['web'],
        'api_middleware' => ['api'],
        'view' => 'connex::login',
        'require_terms' => true,

        /*
         * Without a ?client=, the page signs the visitor into this session guard
         * and redirects to redirect_to: for apps whose frontend is on this domain.
         * Set allow_session_login to false to require a client.
         */
        'allow_session_login' => true,
        'guard' => 'web',
        'redirect_to' => '/',

        /* id of the phone step's submit button, watched by the anti-fraud script. */
        'target_element' => 'connex-submit',
        'gateway_timeout_ms' => 15000,
        'resend_cooldown' => 60,

        /*
         * Requests per minute. Libyan carriers put many subscribers behind one
         * IP, so OTP and confirm limits are per phone number first.
         */
        'throttle' => [
            'script_per_ip' => 60,
            'otp_per_msisdn' => 3,
            'otp_per_ip' => 60,
            'confirm_per_msisdn' => 5,
            'confirm_per_ip' => 120,
            'token_per_ip' => 60,
        ],
    ],

    'account' => [
        'enabled' => true,
        'middleware' => ['web', 'auth'],
        'api_middleware' => ['api', 'auth:sanctum'],
        'view' => 'connex::account',
        /* Session guard the webview account ticket signs into. */
        'guard' => 'web',
        'ticket_ttl' => 120,
        'logout_url' => null,
    ],

    'pages' => [
        'brand_name' => env('APP_NAME', 'Connex'),
        'brand_color' => '#c8333b',
        'logo_url' => null,
        /* Extra stylesheets loaded after the bundled CSS, to restyle without publishing views. */
        'stylesheets' => [],
        'layout' => 'connex::layouts.page',
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
