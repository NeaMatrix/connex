<?php

declare(strict_types=1);

namespace NeaMatrix\Connex;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use NeaMatrix\Connex\Auth\DefaultLoginResponder;
use NeaMatrix\Connex\Auth\EloquentUserResolver;
use NeaMatrix\Connex\Auth\OneTimeCodes;
use NeaMatrix\Connex\Auth\SanctumTokenIssuer;
use NeaMatrix\Connex\Auth\TokenResponseBuilder;
use NeaMatrix\Connex\Client\AccessTokenProvider;
use NeaMatrix\Connex\Client\ClientOptions;
use NeaMatrix\Connex\Client\HttpConnexClient;
use NeaMatrix\Connex\Client\ResponseParser;
use NeaMatrix\Connex\Console\CheckConnectionCommand;
use NeaMatrix\Connex\Contracts\BuildsTokenResponse;
use NeaMatrix\Connex\Contracts\ConnexClient;
use NeaMatrix\Connex\Contracts\IssuesTokens;
use NeaMatrix\Connex\Contracts\LoginResponder;
use NeaMatrix\Connex\Contracts\ResolvesUsers;
use NeaMatrix\Connex\Http\Middleware\EnsureConnexAccess;
use NeaMatrix\Connex\Http\RateLimits;
use NeaMatrix\Connex\Login\Clients\ClientRegistry;
use NeaMatrix\Connex\Login\LoginCompleter;
use NeaMatrix\Connex\Login\LoginFlow;
use NeaMatrix\Connex\Login\LoginSession;
use NeaMatrix\Connex\Subscriptions\SubscriptionManager;
use NeaMatrix\Connex\Subscriptions\SubscriptionStore;
use NeaMatrix\Connex\Support\MsisdnNormalizer;
use NeaMatrix\Connex\Testing\FakeConnexClient;
use NeaMatrix\Connex\Webhooks\WebhookProcessor;

class ConnexServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/connex.php', 'connex');

        $this->registerClient();
        $this->registerLogin();
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'connex');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'connex');

        $this->app->make(Router::class)->aliasMiddleware('connex.access', EnsureConnexAccess::class);

        RateLimits::register();
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
            $this->commands([CheckConnectionCommand::class]);
        }
    }

    private function registerClient(): void
    {
        $this->app->singleton(ClientOptions::class, fn (Application $app): ClientOptions => ClientOptions::fromConfig($this->config()));
        $this->app->singleton(MsisdnNormalizer::class, fn (): MsisdnNormalizer => MsisdnNormalizer::fromConfig($this->config()['msisdn'] ?? []));
        $this->app->singleton(ResponseParser::class, fn (): ResponseParser => new ResponseParser((string) config('connex.timezone')));

        // Not singletons: Http::fake() in an app's tests must reach the client.
        $this->app->bind(AccessTokenProvider::class, fn (Application $app): AccessTokenProvider => new AccessTokenProvider(
            $app->make(HttpFactory::class),
            $app->make('cache')->store(config('connex.cache_store')),
            $app->make(ResponseParser::class),
            $app->make(ClientOptions::class),
        ));

        $this->app->bind(ConnexClient::class, HttpConnexClient::class);

        if ($this->usesFakeConnex()) {
            $this->app->scoped(ConnexClient::class, fn (): FakeConnexClient => FakeConnexClient::seededFromDatabase(
                (string) config('connex.fake.otp', '1234'),
            ));
        }

        $this->app->bind(WebhookProcessor::class, fn (Application $app): WebhookProcessor => new WebhookProcessor(
            $app->make(SubscriptionStore::class),
            $app->make('events'),
            $app->make('db.connection'),
            (bool) config('connex.subscriptions.store', true),
        ));
    }

    private function registerLogin(): void
    {
        $this->app->bind(LoginSession::class, fn (Application $app): LoginSession => new LoginSession($app->make('session.store')));
        $this->app->singleton(ClientRegistry::class, fn (): ClientRegistry => ClientRegistry::fromConfig((array) config('connex.clients', [])));
        $this->app->bind(OneTimeCodes::class, fn (Application $app): OneTimeCodes => new OneTimeCodes($app->make('cache')->store(config('connex.cache_store'))));

        $this->app->bind(LoginFlow::class, fn (Application $app): LoginFlow => new LoginFlow(
            $app->make(ConnexClient::class),
            $app->make(MsisdnNormalizer::class),
            $app->make(SubscriptionStore::class),
            $app->make(ResolvesUsers::class),
            $app->make('events'),
            $app->make('db.connection'),
            (bool) config('connex.subscriptions.store', true),
        ));

        $this->app->bindIf(ResolvesUsers::class, fn (): ResolvesUsers => new EloquentUserResolver(
            (string) config('connex.user.model'),
            (string) config('connex.user.msisdn_column', 'phone'),
            config('connex.user.format') === 'national' ? 'national' : 'international',
            config('connex.user.password_column'),
        ));

        $this->app->bindIf(LoginResponder::class, fn (Application $app): LoginResponder => new DefaultLoginResponder(
            $app->make('auth'),
            $app->make(OneTimeCodes::class),
            (int) config('connex.tokens.code_ttl', 120),
            config('connex.login.guard'),
            (string) config('connex.login.redirect_to', '/'),
        ));

        $this->app->bindIf(IssuesTokens::class, fn (): IssuesTokens => new SanctumTokenIssuer(
            (string) config('connex.tokens.name', 'connex'),
            array_values((array) config('connex.tokens.abilities', ['*'])),
            is_numeric(config('connex.tokens.expires_in')) ? (int) config('connex.tokens.expires_in') : null,
        ));

        $this->app->bindIf(BuildsTokenResponse::class, TokenResponseBuilder::class);

        $this->app->bind(LoginCompleter::class, fn (Application $app): LoginCompleter => new LoginCompleter(
            $app->make(LoginResponder::class),
            $app->make(SubscriptionManager::class),
            (bool) config('connex.login.require_access', false),
        ));
    }

    private function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $prefix = (string) config('connex.login.prefix', 'connex');
        $group = fn (string $middlewareKey, array $default, string $file) => Route::prefix($prefix)
            ->middleware(config($middlewareKey, $default))
            ->group(__DIR__."/../routes/{$file}.php");

        if (config('connex.login.enabled')) {
            $group('connex.login.middleware', ['web'], 'login');
            $group('connex.login.api_middleware', ['api'], 'token');
        }

        if (config('connex.account.enabled')) {
            $group('connex.login.middleware', ['web'], 'account-enter');
            $group('connex.account.middleware', ['web', 'auth'], 'account');
            $group('connex.account.api_middleware', ['api', 'auth:sanctum'], 'account-api');
        }

        if (config('connex.webhooks.enabled')) {
            Route::prefix((string) config('connex.webhooks.prefix', 'connex/webhooks'))
                ->middleware(config('connex.webhooks.middleware', ['api']))
                ->group(__DIR__.'/../routes/webhooks.php');
        }
    }

    private function registerPublishing(): void
    {
        $this->publishes([__DIR__.'/../config/connex.php' => config_path('connex.php')], 'connex-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations/create_connex_tables.php.stub' => database_path('migrations/'.date('Y_m_d_His').'_create_connex_tables.php'),
        ], 'connex-migrations');

        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/connex')], 'connex-views');
        $this->publishes([__DIR__.'/../lang' => $this->app->langPath('vendor/connex')], 'connex-translations');
        $this->publishes([
            __DIR__.'/../resources/css' => resource_path('vendor/connex/css'),
            __DIR__.'/../resources/js' => resource_path('vendor/connex/js'),
        ], 'connex-assets');
    }

    /** Fake mode is for local and staging; production always talks to Connex. */
    private function usesFakeConnex(): bool
    {
        return (bool) config('connex.fake.enabled', false) && ! $this->app->isProduction();
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        /** @var array<string, mixed> */
        return config('connex', []);
    }
}
