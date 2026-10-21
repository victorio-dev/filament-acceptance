<?php

declare(strict_types=1);

namespace Victorio\Acceptance\Exceptions;

use RuntimeException;
use Victorio\Acceptance\Models\SealedVersion;

/**
 * The consumer's transition refused the decision (e.g. the quote expired); nothing was written.
 */
final class DecisionRefused extends RuntimeException
{
    public static function for(SealedVersion $version): self
    {
        return new self(sprintf('The decision on sealed version %s was refused by its transition.', $version->getKey()));
    }
}
