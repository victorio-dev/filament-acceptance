<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Exceptions;

use LogicException;

/**
 * Evidence (a sealed version) was about to be deleted through Eloquent. The package never deletes
 * evidence; removing it is a deliberate host decision outside Eloquent (design.md a, Retention).
 */
final class EvidenceIsPermanent extends LogicException
{
    public static function make(string $operation): self
    {
        return new self(sprintf('A sealed version is evidence and cannot be deleted (%s).', $operation));
    }
}
