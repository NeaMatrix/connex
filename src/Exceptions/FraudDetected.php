<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Exceptions;

/**
 * Connex's anti-fraud check blocked the request (messageCode 300).
 */
class FraudDetected extends RequestRejected {}
