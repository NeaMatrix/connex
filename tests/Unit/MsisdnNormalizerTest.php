<?php

declare(strict_types=1);

use NeaMatrix\Connex\Exceptions\InvalidMsisdn;
use NeaMatrix\Connex\Support\MsisdnNormalizer;

it('normalises every common way of writing a Libyan mobile number', function (string $input): void {
    expect((new MsisdnNormalizer)->normalize($input)->international)->toBe('218920920110');
})->with([
    'national with trunk zero' => '0920920110',
    'national without zero' => '920920110',
    'international' => '218920920110',
    'plus prefix with spaces' => '+218 92 092 0110',
    'double zero prefix' => '00218920920110',
    'dashes' => '092-092-0110',
]);

it('rejects numbers that are not mobile numbers', function (string $input): void {
    (new MsisdnNormalizer)->normalize($input);
})->throws(InvalidMsisdn::class)->with([
    'too short' => '09209201',
    'landline prefix' => '0213334444',
    'letters' => 'abc',
    'empty' => '',
]);

it('formats national and masked forms', function (): void {
    $msisdn = (new MsisdnNormalizer)->normalize('0920920110');

    expect($msisdn->national())->toBe('0920920110')
        ->and($msisdn->masked())->toBe('21892*****10');
});
