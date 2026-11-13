<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use NeaMatrix\Connex\Models\ConnexSubscription;
use NeaMatrix\Connex\Tests\Fixtures\User;

beforeEach(function (): void {
    Route::middleware(['web', 'connex.access'])->get('/paid', fn () => 'paid content');
});

function member(?string $state): User
{
    $user = User::query()->create(['phone' => '218920920110', 'password' => 'secret']);

    if ($state !== null) {
        ConnexSubscription::factory()->{$state}()->ownedBy($user)->create(['msisdn' => '218920920110']);
    }

    return $user;
}

it('lets paying and grace-period subscribers through', function (string $state): void {
    $this->actingAs(member($state))->get('/paid')->assertOk()->assertSee('paid content');
})->with(['active', 'cancelled']);

it('blocks everyone else with the Connex reason', function (?string $state, string $reason): void {
    $this->actingAs(member($state))
        ->getJson('/paid')
        ->assertStatus(402)
        ->assertJson(['access' => $reason]);
})->with([
    'renewal failed' => ['unpaid', 'insufficient_balance'],
    'expired' => ['expired', 'expired'],
    'never subscribed' => [null, 'none'],
]);

it('sends browsers to the account page', function (): void {
    $this->actingAs(member('expired'))->get('/paid')->assertRedirect(route('connex.account'));
});
