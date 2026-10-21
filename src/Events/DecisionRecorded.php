<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Victorio\Acceptance\Models\Decision;

/**
 * A decision (accept or decline) was recorded. Dispatched after the surrounding transaction commits, never for a
 * decision that was rolled back.
 */
final class DecisionRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Decision $decision,
    ) {}
}
