<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Webhooks\Notifications;

use NeaMatrix\Connex\Enums\WebhookType;
use NeaMatrix\Connex\Support\Payload;

/**
 * A validated notification pushed by Connex.
 */
interface WebhookNotification
{
    public static function type(): WebhookType;

    /**
     * Laravel validation rules for the raw form fields.
     *
     * @return array<string, list<string>>
     */
    public static function rules(): array;

    public static function fromPayload(Payload $payload): static;

    public function msisdn(): string;
}
