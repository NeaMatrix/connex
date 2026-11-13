<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use NeaMatrix\Connex\Login\LoginSession;

/**
 * Decides what happens after a successful login: sign in on the web, hand a
 * code to the mobile app, or anything else. Returns the URL to send the browser to.
 */
interface LoginResponder
{
    public function respond(Request $request, Authenticatable $user, LoginSession $session): string;
}
