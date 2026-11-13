<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use NeaMatrix\Connex\Contracts\ConnexClient;
use NeaMatrix\Connex\Enums\SubscriptionStatus;
use NeaMatrix\Connex\Models\ConnexSubscription;
use NeaMatrix\Connex\Testing\FakeConnexClient;
use Workbench\App\Models\User;

/**
 * Runs the package against an in-memory Connex for local demos.
 */
class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConnexClient::class, function (): FakeConnexClient {
            $fake = new FakeConnexClient;

            // Each demo request gets a fresh fake, so replay what is already stored.
            if (Schema::hasTable('connex_subscriptions')) {
                foreach (ConnexSubscription::query()->get() as $subscription) {
                    $fake->withSubscriber($subscription->msisdn, $subscription->status ?? SubscriptionStatus::Active, $subscription->expires_at);
                }
            }

            return $fake;
        });

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'demo');

        config([
            'connex.base_url' => 'https://connex.invalid/external-api',
            'connex.user.model' => User::class,
            'connex.clients' => [
                'demo' => ['redirect_uris' => ['http://127.0.0.1:8000/demo/callback', 'http://127.0.0.1:8765/demo/callback'], 'pkce' => true],
            ],
            'connex.login.redirect_to' => '/connex/account',
            'connex.account.logout_url' => '/logout',
            'connex.pages.terms_url' => '/terms',
            'connex.pages.privacy_url' => '/privacy',
            'connex.pages.contact_url' => '/contact',
        ]);
    }
}
