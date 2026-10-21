<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Exceptions;

use LogicException;

/**
 * A sealed version was about to be changed through Eloquent. Seal a new version instead.
 */
final class SealedVersionIsImmutable extends LogicException
{
    public static function make(string $operation): self
    {
        return new self(sprintf('A sealed version cannot be changed (%s); seal a new version instead.', $operation));
    }
}
