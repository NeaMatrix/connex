<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
 * /demo plays the part of a separate frontend (or mobile app): it starts the
 * login with PKCE, receives the code on /demo/callback, exchanges it for a
 * Sanctum token and calls a protected API with it.
 */
Route::view('/demo', 'demo::frontend')->name('demo');
Route::view('/demo/callback', 'demo::frontend')->name('demo.callback');

Route::get('/api/me', fn (Request $request) => [
    'id' => $request->user()?->getKey(),
    'phone' => $request->user()?->getAttribute('phone'),
    'has_connex_access' => $request->user()?->hasConnexAccess(),
])->middleware(['api', 'auth:sanctum']);

Route::redirect('/', '/demo');
Route::get('/login', fn () => redirect('/connex/login'))->name('login');

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();

    return redirect('/demo');
})->middleware('web');
