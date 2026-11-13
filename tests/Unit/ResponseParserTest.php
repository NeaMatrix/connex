<?php

declare(strict_types=1);

use NeaMatrix\Connex\Client\ResponseParser;
use NeaMatrix\Connex\Enums\Endpoint;
use NeaMatrix\Connex\Enums\MessageCode;
use NeaMatrix\Connex\Exceptions\UnexpectedResponse;

it('reads the payload from whichever wrapper key Connex used', function (string $key): void {
    $response = (new ResponseParser)->parse(Endpoint::Login, 200, json_encode(['messageCode' => '00', $key => ['msisdn' => '218920920110']]));

    expect($response->payload->string('msisdn'))->toBe('218920920110')
        ->and($response->code)->toBe(MessageCode::Success);
})->with(['success', 'failed', 'data']);

it('tolerates the trailing commas found in Connex responses', function (): void {
    $body = '{ "messageCode": "01", "success": { "message": "OTP incorrect", "msisdn": "218920920110", } }';

    $response = (new ResponseParser)->parse(Endpoint::LoginConfirm, 422, $body);

    expect($response->message())->toBe('OTP incorrect')
        ->and($response->isSuccessful())->toBeFalse();
});

it('treats an unknown message code as a failure', function (): void {
    $response = (new ResponseParser)->parse(Endpoint::Login, 200, '{"messageCode":"99"}');

    expect($response->code)->toBeNull()
        ->and($response->rawCode)->toBe('99')
        ->and($response->isSuccessful())->toBeFalse();
});

it('throws on bodies that are not Connex responses', function (string $body): void {
    (new ResponseParser)->parse(Endpoint::Login, 502, $body);
})->throws(UnexpectedResponse::class)->with(['<html>Bad Gateway</html>', '{"ok":true}', '']);
