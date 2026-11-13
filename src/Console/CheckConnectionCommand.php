<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Console;

use Illuminate\Console\Command;
use NeaMatrix\Connex\Client\AccessTokenProvider;
use NeaMatrix\Connex\Contracts\ConnexClient;
use NeaMatrix\Connex\Exceptions\ConnexException;

/**
 * php artisan connex:check — verifies the base URL and credentials work.
 */
class CheckConnectionCommand extends Command
{
    protected $signature = 'connex:check {--msisdn= : Also fetch this subscriber\'s details}';

    protected $description = 'Check that the Connex credentials and endpoint are working';

    public function handle(AccessTokenProvider $tokens, ConnexClient $connex): int
    {
        $this->components->info('Connex: '.config('connex.base_url'));

        try {
            $tokens->forget();
            $this->components->task('Authenticate (auth-login)', fn (): bool => $tokens->token() !== '');
            $this->components->task('Anti-fraud script (protected-script)', fn (): bool => $connex->protectedScript('#connex-submit')->script !== '');

            $msisdn = $this->option('msisdn');

            if (is_string($msisdn) && $msisdn !== '') {
                $details = $connex->subscriberDetails($msisdn);
                $this->components->twoColumnDetail('Status', $details->status->value ?? 'none');
                $this->components->twoColumnDetail('Expires', $details->expiresAt?->toDateTimeString() ?? '—');
            }
        } catch (ConnexException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
