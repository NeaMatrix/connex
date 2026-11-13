<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Exceptions;

/**
 * An OTP was requested again before Connex allows it.
 */
class TooManyRequests extends RequestRejected {}
