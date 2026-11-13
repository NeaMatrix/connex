<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Login;

/**
 * Where the login page goes next. For popup logins (response_mode=web_message)
 * the page posts $message to window.opener at $targetOrigin instead.
 */
final readonly class LoginRedirect
{
    /**
     * @param  array<string, string|null>|null  $message
     */
    public function __construct(
        public string $url,
        public ?array $message = null,
        public ?string $targetOrigin = null,
    ) {}

    /**
     * @return array{redirect: string, web_message?: array{target_origin: string|null, data: array<string, string|null>}}
     */
    public function toArray(): array
    {
        $data = ['redirect' => $this->url];

        if ($this->message !== null) {
            $data['web_message'] = ['target_origin' => $this->targetOrigin, 'data' => $this->message];
        }

        return $data;
    }
}
