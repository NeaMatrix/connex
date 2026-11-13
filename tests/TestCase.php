<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\SanctumServiceProvider;
use NeaMatrix\Connex\ConnexServiceProvider;
use NeaMatrix\Connex\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public const string BASE_URL = 'https://connex.test/external-api';

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [SanctumServiceProvider::class, ConnexServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set([
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'database.default' => 'testing',
            'cache.default' => 'array',
            'session.driver' => 'array',
            'connex.base_url' => self::BASE_URL,
            'connex.credentials.email' => 'service@example.com',
            'connex.credentials.password' => 'service-password',
            'connex.user.model' => User::class,
            'connex.webhooks.secret' => 'webhook-secret',
            'connex.clients' => [
                'mobile' => ['redirect_uris' => ['myapp://auth/connex'], 'pkce' => true],
                'web' => ['redirect_uris' => ['https://app.example.com/auth/callback'], 'pkce' => false],
            ],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Many apps enable this outside production; the package must work with it.
        Model::preventLazyLoading();
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        (require __DIR__.'/../database/migrations/create_connex_tables.php.stub')->up();
        $this->loadMigrationsFrom(__DIR__.'/../vendor/laravel/sanctum/database/migrations');
    }
}
