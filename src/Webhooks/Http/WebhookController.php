<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Webhooks\Http;

use Illuminate\Contracts\Validation\Factory as Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NeaMatrix\Connex\Enums\WebhookType;
use NeaMatrix\Connex\Support\MsisdnNormalizer;
use NeaMatrix\Connex\Support\Payload;
use NeaMatrix\Connex\Webhooks\Notifications\Reactivated;
use NeaMatrix\Connex\Webhooks\Notifications\Renewed;
use NeaMatrix\Connex\Webhooks\Notifications\Subscribed;
use NeaMatrix\Connex\Webhooks\Notifications\Unsubscribed;
use NeaMatrix\Connex\Webhooks\Notifications\WebhookNotification;
use NeaMatrix\Connex\Webhooks\WebhookProcessor;

/**
 * Receives POST {prefix}/{subscribe|unsubscribe|reactivate|renewal} from Connex
 * and answers in the format Connex expects.
 */
class WebhookController
{
    /** @var array<string, class-string<WebhookNotification>> */
    private const array NOTIFICATIONS = [
        'subscribe' => Subscribed::class,
        'unsubscribe' => Unsubscribed::class,
        'reactivate' => Reactivated::class,
        'renewal' => Renewed::class,
    ];

    public function __invoke(
        Request $request,
        string $type,
        Validator $validator,
        WebhookProcessor $processor,
        MsisdnNormalizer $msisdns,
    ): JsonResponse {
        $notificationClass = self::NOTIFICATIONS[$type];
        $validation = $validator->make($request->all(), $notificationClass::rules());

        if ($validation->fails() || ! $msisdns->isValid((string) $request->input('msisdn'))) {
            return response()->json([
                'messageCode' => '01',
                'message' => 'invalid payload',
                'errors' => $validation->errors()->toArray(),
            ], 422);
        }

        $fields = $validation->validated();
        $fields['msisdn'] = $msisdns->normalize((string) $fields['msisdn'])->international;
        $timezone = (string) config('connex.timezone', 'Africa/Tripoli');

        $processor->process($notificationClass::fromPayload(new Payload($fields, $timezone)));

        return response()->json([
            'messageCode' => '00',
            'message' => WebhookType::from($type)->acknowledgement(),
        ]);
    }
}
