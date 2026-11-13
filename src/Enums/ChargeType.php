<?php

declare(strict_types=1);

namespace NeaMatrix\Connex\Enums;

/**
 * Why a subscriber was charged.
 */
enum ChargeType: string
{
    case InitialBill = 'initial_bill';
    case Renewal = 'renewal';
    case Purchase = 'purchase';
}
