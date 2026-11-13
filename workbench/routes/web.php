<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/connex/login');
Route::get('/login', fn () => redirect('/connex/login'))->name('login');

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();

    return redirect('/connex/login');
})->middleware('web');

Route::get('/paid', fn () => 'Paid content')->middleware(['web', 'auth', 'connex.access']);
