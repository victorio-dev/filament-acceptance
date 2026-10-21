<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Victorio\Acceptance\Models\SealedVersion;

/**
 * A new version was sealed. Dispatched after the surrounding transaction commits, so a listener
 * never sees a version that was rolled back.
 */
final class VersionSealed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly SealedVersion $version,
    ) {}
}
