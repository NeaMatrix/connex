<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use NeaMatrix\Connex\Client\ClientOptions;
use NeaMatrix\Connex\Contracts\ConnexClient;
use NeaMatrix\Connex\Exceptions\InvalidOtp;
use NeaMatrix\Connex\Tests\Fixtures\ConnexHttp;

it('logs calls without OTPs and with masked numbers', function (): void {
    config(['connex.logging.channel' => 'connex-test', 'logging.channels.connex-test' => ['driver' => 'single', 'path' => storage_path('logs/connex-test.log')]]);
    app()->forgetInstance(ClientOptions::class);
    Log::shouldReceive('channel')->with('connex-test')->andReturnSelf();
    Log::shouldReceive('info')->once()->withArgs(fn (string $message, array $context): bool => $message === 'Connex login-confirm'
        && $context['request']['msisdn'] === '21892*****10'
        && ! array_key_exists('otp', $context['request'])
        && $context['message_code'] === '01');

    ConnexHttp::fake(['login-confirm' => [['messageCode' => '01', 'failed' => ['message' => 'OTP incorrect']], 422]]);

    expect(fn () => app(ConnexClient::class)->confirmLogin('0920920110', '4567'))->toThrow(InvalidOtp::class);
});
