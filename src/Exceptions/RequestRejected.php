<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Exceptions;

/**
 * Connex understood the request and refused it, for a reason that has no more
 * specific exception.
 */
class RequestRejected extends ConnexException {}
