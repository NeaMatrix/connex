<?php

declare(strict_types=1);

use NeaMatrix\Connex\Client\ResponseParser;
use NeaMatrix\Connex\Enums\Endpoint;
use NeaMatrix\Connex\Exceptions\AuthenticationFailed;
use NeaMatrix\Connex\Exceptions\ExceptionFactory;
use NeaMatrix\Connex\Exceptions\FraudDetected;
use NeaMatrix\Connex\Exceptions\InsufficientBalance;
use NeaMatrix\Connex\Exceptions\InvalidOtp;
use NeaMatrix\Connex\Exceptions\InvalidProduct;
use NeaMatrix\Connex\Exceptions\RequestRejected;
use NeaMatrix\Connex\Exceptions\SubscriptionAlreadyActive;
use NeaMatrix\Connex\Exceptions\SubscriptionAlreadyCancelled;
use NeaMatrix\Connex\Exceptions\TooManyRequests;

it('maps each rejection to its exception', function (Endpoint $endpoint, int $status, string $code, string $exception): void {
    $response = (new ResponseParser)->parse($endpoint, $status, json_encode(['messageCode' => $code, 'failed' => ['message' => 'x']]));

    expect(ExceptionFactory::fromResponse($response))->toBeInstanceOf($exception);
})->with([
    'fraud' => [Endpoint::Login, 200, '300', FraudDetected::class],
    'otp rate limit' => [Endpoint::Login, 429, '05', TooManyRequests::class],
    'wrong otp on login' => [Endpoint::LoginConfirm, 422, '01', InvalidOtp::class],
    'wrong otp on cancel' => [Endpoint::UnsubscribeConfirm, 422, '01', InvalidOtp::class],
    'already active' => [Endpoint::SubscriptionActivation, 406, '01', SubscriptionAlreadyActive::class],
    'already cancelled' => [Endpoint::Unsubscribe, 406, '12', SubscriptionAlreadyCancelled::class],
    'no balance' => [Endpoint::LoginConfirm, 406, '21', InsufficientBalance::class],
    'bad product' => [Endpoint::BuyProduct, 404, '18', InvalidProduct::class],
    'bad service credentials' => [Endpoint::AuthLogin, 401, '01', AuthenticationFailed::class],
    'expired service token' => [Endpoint::Login, 401, '01', AuthenticationFailed::class],
    'anything else' => [Endpoint::SubscriberDetails, 400, '01', RequestRejected::class],
]);

it('keeps the Connex message and response on the exception', function (): void {
    $response = (new ResponseParser)->parse(Endpoint::Login, 200, '{"messageCode":"300","failed":{"message":"This operation is not allowed"}}');

    $exception = ExceptionFactory::fromResponse($response);

    expect($exception->getMessage())->toBe('This operation is not allowed')
        ->and($exception->response)->toBe($response);
});
