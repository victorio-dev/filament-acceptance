<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Enums;

enum DecisionKind: string
{
    case Accepted = 'accepted';
    case Declined = 'declined';
}
