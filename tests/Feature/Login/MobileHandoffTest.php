<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use NeaMatrix\Connex\Auth\IssuedToken;
use NeaMatrix\Connex\Contracts\IssuesTokens;
use NeaMatrix\Connex\Enums\DeviceType;
use NeaMatrix\Connex\Facades\Connex;
use NeaMatrix\Connex\Tests\Fixtures\User;

beforeEach(function (): void {
    app()->instance(IssuesTokens::class, new class implements IssuesTokens
    {
        public function issue(Authenticatable $user, DeviceType $device): IssuedToken
        {
            return new IssuedToken("token-for-{$user->getAuthIdentifier()}-{$device->value}");
        }
    });
});

function loginFromApp(): string
{
    test()->get('/connex/login?device=android');
    test()->getJson('/connex/login/script');
    test()->postJson('/connex/login/otp', ['msisdn' => '0920920110', 'accepted_terms' => true]);

    return test()->postJson('/connex/login/otp/confirm', ['otp' => '1234'])->assertOk()->json('redirect');
}

it('hands the app a single-use code instead of a token', function (): void {
    $connex = Connex::fake();

    $redirect = loginFromApp();

    expect($redirect)->toStartWith('myapp://connex?code=')->not->toContain('token');
    $this->assertGuest();
    $connex->assertCalled('confirmLogin', fn (array $call): bool => $call['device'] === DeviceType::Android);
});

it('exchanges the code for an API token exactly once', function (): void {
    Connex::fake();
    parse_str((string) parse_url(loginFromApp(), PHP_URL_QUERY), $query);
    $user = User::query()->sole();

    $this->postJson('/connex/api/handoff', ['code' => $query['code']])
        ->assertOk()
        ->assertExactJson(['token' => "token-for-{$user->id}-android", 'token_type' => 'Bearer', 'expires_at' => null]);

    $this->postJson('/connex/api/handoff', ['code' => $query['code']])
        ->assertStatus(422)
        ->assertJson(['error' => 'invalid_code']);
});

it('expires unused codes', function (): void {
    Connex::fake();
    parse_str((string) parse_url(loginFromApp(), PHP_URL_QUERY), $query);

    $this->travel(121)->seconds();

    $this->postJson('/connex/api/handoff', ['code' => $query['code']])->assertStatus(422);
});
