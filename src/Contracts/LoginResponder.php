<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Contracts;

use Illuminate\Http\Request;
use NeaMatrix\Connex\Auth\DefaultLoginResponder;
use NeaMatrix\Connex\Login\LoginRedirect;
use NeaMatrix\Connex\Login\LoginResult;
use NeaMatrix\Connex\Login\LoginSession;

/**
 * Decides what happens after a successful login. The default hands clients a
 * one-time code ({@see DefaultLoginResponder}).
 */
interface LoginResponder
{
    public function respond(Request $request, LoginResult $result, LoginSession $session): LoginRedirect;
}
