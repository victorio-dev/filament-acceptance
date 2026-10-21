<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Exceptions;

use RuntimeException;
use Victorio\Acceptance\Models\Decision;
use Victorio\Acceptance\Models\SealedVersion;

/**
 * The version already has a decision; there is only one per version. `$decision` is the existing
 * one when it could be read (after a lost race on PostgreSQL the transaction cannot read it).
 */
final class DecisionAlreadyMade extends RuntimeException
{
    public ?Decision $decision = null;

    public static function for(SealedVersion $version, ?Decision $decision = null): self
    {
        $exception = new self(sprintf('Sealed version %s already has a decision.', $version->getKey()));
        $exception->decision = $decision;

        return $exception;
    }
}
