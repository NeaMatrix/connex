<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Client;

use NeaMatrix\Connex\Enums\Endpoint;
use NeaMatrix\Connex\Enums\MessageCode;
use NeaMatrix\Connex\Exceptions\UnexpectedResponse;
use NeaMatrix\Connex\Support\Payload;

/**
 * Decodes raw Connex HTTP bodies into {@see ConnexResponse}.
 */
final readonly class ResponseParser
{
    private const array PAYLOAD_KEYS = ['success', 'failed', 'data', 'errors'];

    public function __construct(
        private string $timezone = 'Africa/Tripoli',
    ) {}

    /**
     * @throws UnexpectedResponse
     */
    public function parse(Endpoint $endpoint, int $httpStatus, string $body): ConnexResponse
    {
        $decoded = $this->decode($body);

        if ($decoded === null || ! array_key_exists('messageCode', $decoded)) {
            throw UnexpectedResponse::undecodable($endpoint, $httpStatus, $body);
        }

        $rawCode = is_scalar($decoded['messageCode']) ? (string) $decoded['messageCode'] : '';

        return new ConnexResponse(
            endpoint: $endpoint,
            httpStatus: $httpStatus,
            code: MessageCode::tryFrom($rawCode),
            rawCode: $rawCode,
            payload: new Payload($this->payloadOf($decoded), $this->timezone),
            body: $body,
        );
    }

    /**
     * Connex's own examples contain trailing commas, so a strict decode is
     * retried once after removing them.
     *
     * @return array<string, mixed>|null
     */
    private function decode(string $body): ?array
    {
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            $decoded = json_decode(preg_replace('/,\s*([}\]])/', '$1', $body) ?? '', true);
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    private function payloadOf(array $decoded): array
    {
        foreach (self::PAYLOAD_KEYS as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                return $decoded[$key];
            }
        }

        return array_diff_key($decoded, ['messageCode' => true]);
    }
}
